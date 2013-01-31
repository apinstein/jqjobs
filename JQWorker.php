<?php
// vim: set expandtab tabstop=4 shiftwidth=4:
/**
 * A simple worker class.
 *
 * To use, simple do:
 * <code>
 * $worker = new JQWorker($queue);
 * $worker->run();
 *
 * You can configure the worker to some extent with the options parameter to the constructor.
 */
class JQWorker
{
    public $workerId = NULL;

    protected $jqStore;
    protected $options;
    protected $okToRun = true;
    protected $pid = NULL;
    protected $jobsProcessed = 0;
    protected $allIncludedFiles = NULL;

    private $currentJob = NULL;
    private $controlCAlready = false;

    const EXIT_CODE_MEMORY = 1;
    const EXIT_CODE_CODE_CHANGED = 2;
    const EXIT_CODE_SIGNAL = 3;

    /**
     * Create a JQWorker.
     *
     * @param object JQStore The backing store to use to get/save jobs.
     * @param array Options:
     *              - queueName (string): which queue to pull jobs from; ALL queues by default.
     *              - wakeupEvery (int): the worker sleeps if there are no jobs; it will wake up every N seconds to poll for new jobs.
     *              - verbose (boolean): Output information about each job being run.
     *              - guaranteeMemoryForJob (int): The worker will exit if there is not at least this much memory available before running the next job.
     *                                             Since the worker is usually managed by a process supervisor that will restart it when it dies,
     *                                             this preventative measure effectively eliminates OOM's while jobs are running, which leave
     *                                             an orphaned "running" job. This is a highly undesireable state.
     *                                             DEFAULT: will require 20% of memory limit still available.
     *              - exitIfNoJobs (boolean): Should the worker exit if no jobs remain. DEFAULT: false.
     *              - exitAfterNJobs (int): The worker will exit after N jobs have been processed. Set to NULL to run forever. DEFAULT: NULL.
     *              - adjustPriority (int): An integer value used to adjust the priority (see proc_nice). Postive integers reduce priority. Negative integers increase priority (requires root).
     *                                      A adjustPriority of 10 is reasonable for background processes.
     *              - gracefulShutdownTimeout (int): Number of seconds to allow jobs to finish before killing them forcefully. DEFAULT: 5.
     */
    public function __construct($jqStore, $options = array())
    {
        $this->workerId = uniqid();
        $this->jqStore = $jqStore;
        $this->options = array_merge(array(
                                            'queueName'                 => NULL,
                                            'wakeupEvery'               => 5,
                                            'verbose'                   => false,
                                            'silent'                    => false,
                                            'guaranteeMemoryForJob'     => 0.2 * $this->getMemoryLimitInBytes(),
                                            'exitIfNoJobs'              => false,
                                            'exitAfterNJobs'            => NULL,
                                            'adjustPriority'            => NULL,
                                            'gracefulShutdownTimeout'   => 5,
                                          ),
                                     $options
                                    );
        $this->pid = getmypid();
        $this->log("pid = {$this->pid}");

        // install signal handlers if possible
        declare(ticks = 1);
        if (function_exists('pcntl_signal'))
        {
            foreach (array(SIGHUP, SIGINT, SIGQUIT, SIGABRT, SIGTERM, SIGALRM) as $signal) {
                pcntl_signal($signal, array($this, 'signalHandler'));
            }
        }
    }

    protected function logJobStatus($job, $msg, $verboseOnly = false)
    {
        $msg = "[Job: {$job->getJobId()} {$job->getStatus()} attempt {$job->getAttemptNumber()}/{$job->getMaxAttempts()}] {$msg}";
        $this->log($msg, $verboseOnly);
    }
   
    protected function log($msg, $verboseOnly = false)
    {
        if ($this->options['silent']) return;
        if ($verboseOnly and !$this->options['verbose']) return;
        print "[Worker: {$this->workerId}] {$msg}\n";
    }

    public function jobsProcessed()
    {
        return $this->jobsProcessed;
    }

    /**
     * Call proc_nice with passed value if available on this platform.
     *
     * @param int Priorty adjustment to be made.
     * @return boolean True if successful.
     *
     * @see options['adjustPriority']
     * @see http://us3.php.net/manual/en/function.proc-nice.php
     **/
    public function adjustPriority($adjBy)
    {
        if (!function_exists('proc_nice')) return;

        return proc_nice($adjBy);
    }

    /**
     * Starts the worker process.
     *
     * Blocks until the worker exists.
     */
    public function start()
    {
        $this->log("Starting worker process on queue: " . ($this->options['queueName'] === NULL ? '(any)' : $this->options['queueName']));;

        if (isset($this->options['adjustPriority']))
        {
            $this->adjustPriority($this->options['adjustPriority']);
        }

        $this->okToRun = true;
        try {
            while ($this->okToRun) {
                $this->memCheck();
                $this->codeCheck();

                $this->currentJob = $this->jqStore->next($this->options['queueName']);
                if ($this->currentJob)
                {
                    $this->logJobStatus($this->currentJob, $this->currentJob->description(), true);

                    try {
                        $result = $this->currentJob->run($this->currentJob);

                        if ($result === NULL)
                        {
                            $this->logJobStatus($this->currentJob, "Done!", true);
                        }
                        else
                        {
                            $this->logJobStatus($this->currentJob, $result);
                        }

                        $this->currentJob = NULL;
                    } catch (JQWorker_SignalException $e) {
                        $result = $e->getMessage();
                        $this->logJobStatus($this->currentJob, "signal raised during job->run()");
                        $this->gracefullyRetryCurrentJob($this->currentJob);
                        $this->currentJob = NULL;
                        throw $e;
                    }

                    $this->jobsProcessed++;
                    if ($this->options['exitAfterNJobs'] && $this->jobsProcessed >= $this->options['exitAfterNJobs'])
                    {
                        break;
                    }
                }
                else
                {
                    $this->log("No jobs available.");
                    if ($this->options['exitIfNoJobs'])
                    {
                        $this->log("Exiting since exitIfNoJobs=true");
                        break;
                    }
                    else
                    {
                        $this->log("Sleeping for {$this->options['wakeupEvery']} seconds...");
                        $ok = sleep($this->options['wakeupEvery']);
                        $this->log('sleep: ' . var_export($ok, true));
                    }
                }
            }
        } catch (JQWorker_SignalException $e) {
            $result = $e->getMessage();
            $this->log("signal raised during run()");
            $this->gracefullyRetryCurrentJob($this->currentJob);
            exit(self::EXIT_CODE_SIGNAL);
        }

        $this->log("Stopping worker process on queue: " . ($this->options['queueName'] === NULL ? '(any)' : $this->options['queueName']));
    }

    private function getMemoryLimitInBytes()
    {
        $val = trim(ini_get('memory_limit'));
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    public function memCheck()
    {
        $oomAt = $this->getMemoryLimitInBytes();
        $now = memory_get_usage();
        $remaining = $oomAt - $now;
        if ( $remaining < $this->options['guaranteeMemoryForJob'] )
        {
            $this->log("JQWorker doesn't have enough memory remaining ({$remaining}) required for next job ({$this->options['guaranteeMemoryForJob']}).");
            exit(self::EXIT_CODE_MEMORY);
        }
    }

    public function codeCheck()
    {
        // make sure we have code mod date for every file in use
        foreach (get_included_files() as $includedFile) {
            if (!isset($this->allIncludedFiles[$includedFile]))
            {
                $this->allIncludedFiles[$includedFile] = filemtime($includedFile);
            }
        }

        // check for out-of-date code
        clearstatcache();
        foreach ($this->allIncludedFiles as $includedFile => $dts) {
            if ($dts != filemtime($includedFile))
            {
                $this->log("JQWorker exiting since we detected updated code in {$includedFile}.");
                exit(self::EXIT_CODE_CODE_CHANGED);
            }
        }
    }

    public function signalHandler($sigNo)
    {
        $this->log("Signal {$sigNo} received.");
        switch ($sigNo) {
            case SIGALRM:
                $this->signalExceptionHandlerForImmediateShutdown($sigNo);
                break;
            case SIGTERM:
            case SIGQUIT:
            case SIGABRT:
                $this->signalExceptionHandlerForGracefulShutdown($sigNo, $this->options['gracefulShutdownTimeout']);
                break;
            case SIGHUP:
                $this->log("HUP received; will gracefully exit. Your supervisor process should auto-restart the worker.");
                $this->stop();
                break;
            case SIGINT:
                if ($this->controlCAlready)
                {
                    $this->log("SIGINT received again; terminating immediately.");
                    $this->signalExceptionHandlerForImmediateShutdown($sigNo);
                }
                else
                {
                    $this->log("SIGINT received; will exit after current job finishes. To exit immediatley, control-c again.");
                    $this->controlCAlready = true;
                    $this->stop();
                }
                break;
            default:
                // ignore
            }
    }

    /**
     * FAILS the current job and exit immediately.
     *
     * This will ask the JQStore to retry the job without affecting the remaining number of retries.
     *
     * Note that you do need enough time to hit the JQStore backend to persist the proper job failure state.
     */
    protected function signalExceptionHandlerForImmediateShutdown($sigNo)
    {
        $this->log("Terminating immediately.");
        if ($this->currentJob)
        {
            throw new JQWorker_SignalException("Caught signal {$sigNo}, forcing immediate job termination.", $sigNo);
        }
        exit(self::EXIT_CODE_SIGNAL);
    }

    /**
     * Give the current job up to N seconds to finish before forcing a shutdown.
     */
    protected function signalExceptionHandlerForGracefulShutdown($sigNo, $secondsToFinish)
    {
        $this->log("Gracefully shutting down; will force hard shutdown in {$secondsToFinish} if necessary.");

        $this->stop();

        // schedule signal for immediate shudown in $secondsToFinish
        pcntl_alarm($secondsToFinish);

        // eat signal -- job will continue on main thread when this re-entrant code ends w/o exit()
        return;
    }

    private function gracefullyRetryCurrentJob($job)
    {
        if ($job)
        {
            $this->logJobStatus($job, "Gracefully re-trying current job due to signal interruption.");
            // don't trust $job; there is a race between when the job *actually* finishes and we can persist it to the JQStore...
            // during this time if there is a failure, the job is in an indeterminate state since PHP doesn't have un-interruptible blocks.
            // THUS in this case we care only if the DB thinks the job is checked out/running; if so, we just retry it.
            // Since we EXIT the script after this block, we don't have to worry about parallel universe collisions.
            // Note that to test this, we need to get the DB version of the job to know what's what.
            $this->jqStore->abort();
            $persistedVersionOfJob = NULL;
            try {
                $persistedVersionOfJob = $this->jqStore->get($job->getJobId());
            } catch (JQStore_JobNotFoundException $e) {
                $this->logJobStatus($job, "Completed job already persisted via JQStore.");
                return;
            }
            if ($persistedVersionOfJob && $persistedVersionOfJob->getStatus() === JQManagedJob::STATUS_RUNNING)
            {
                $this->logJobStatus($persistedVersionOfJob, "Failing job with mulligan.");
                $persistedVersionOfJob->markJobFailed("Worker was asked to terminate immediately.", true);
                $this->logJobStatus($persistedVersionOfJob, "Successfully failed job with mulligan.");
            }
         }
    }

    /**
     * Sets a flag to have the worker exit gracefully after the current job completes.
     */
    public function stop()
    {
        $this->okToRun = false;
        $this->log("Stop requested for worker process on queue: " . ($this->options['queueName'] === NULL ? '(any)' : $this->options['queueName']), true);
    }
}

class JQWorker_SignalException extends Exception {}
