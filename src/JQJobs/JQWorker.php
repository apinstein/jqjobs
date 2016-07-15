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

    // will be non-null if we are currently in a signal handler
    private $currentSignalNo = NULL;

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
     *              - queueName (mixed): which queue to pull jobs from; ALL queues by default.
     *                         (string): a comma-separated list of queue names to pull from.
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
     *              - enableJitter (boolean): Intruduces slight time jitter on start and sleep to prevent accidental DOS/resonance in high-concurrency situations
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
                                            'enableJitter'              => true,
                                          ),
                                     $options
                                    );
        $this->pid = getmypid();
        $this->log("pid = {$this->pid}");

        $this->options['queueName'] = $this->convertCommaSepQueueNameOptionIntoFormatForNext($this->options['queueName']);

        // install signal handlers if possible
        declare(ticks = 1);
        if (function_exists('pcntl_signal'))
        {
            foreach (array(SIGHUP, SIGINT, SIGQUIT, SIGABRT, SIGTERM, SIGALRM) as $signal) {
                pcntl_signal($signal, array($this, 'signalHandler'));
            }
        }
    }

    protected function logQueueStatus($msg, $verboseOnly = false)
    {
        $queueNamesForNextJobFilter = is_array($this->options['queueName']) ? join(',', $this->options['queueName']) : $this->options['queueName'];
        $queueNamesMsg = JQManagedJob::isAnyQueue($queueNamesForNextJobFilter) ? JQManagedJob::QUEUE_ANY : $queueNamesForNextJobFilter;

        $msg = "[queues:{$queueNamesMsg}] {$msg}";
        $this->log($msg, $verboseOnly);
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
     * Convert a comma-sep queue name list to an array of strings.
     *
     * @param string NULL, 'a', or 'a,b'
     * @return mixed NULL, JQManagedJob::QUEUE_ANY, or array of strings.
     */
    private function convertCommaSepQueueNameOptionIntoFormatForNext($commaSep)
    {
        // canonicalize to array
        $names = explode(',', $commaSep);
        // remove surrounding whitespace
        $names = array_map('trim', $names);
        // collapse dupes
        $names = array_unique($names);
        // remove empties
        $names = array_filter($names);

        // special trick for NO items
        if (count($names) === 0)
        {
            return JQManagedJob::QUEUE_ANY;
        }
        else
        {
            return $names;
        }
    }

    /**
     * Starts the worker process.
     *
     * Blocks until the worker exists.
     */
    public function start()
    {
        $this->logQueueStatus("Starting worker process.");

        if ($this->options['enableJitter'])
        {
            $ms = rand(0, 999);
            $this->log("Startup jitter: 0.{$ms} seconds...");
            JQWorker::sleep(0, $ms * 1000000);
        }

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
                    try {
                        $this->logJobStatus($this->currentJob, "Job checked out.");

                        // attempt to un-serialize the job
                        if ($this->currentJob->getJob() instanceof JQJob)
                        {
                            $this->logJobStatus($this->currentJob, $this->currentJob->description());
                            $result = $this->currentJob->run($this->currentJob);
                        }
                        else
                        {
                            $this->currentJob->markJobFailed("JQManagedJob.job is not a JQJob instance.");
                            $result = "No JQJob found.";
                        }

                        if ($result === NULL)
                        {
                            $this->logJobStatus($this->currentJob, "Done!");
                        }
                        else
                        {
                            $this->logJobStatus($this->currentJob, $result);
                        }

                        $this->currentJob = NULL;
                    } catch (Exception $e) {
                        if ($this->currentSignalNo === NULL) throw $e;  // we only handle signals here

                        // This block helps JQJobs minimize the incidence of jobs getting stuck in the "running" state
                        // It is designed to catch exceptions from JQJob->run() trying to record a finished ActualJob->run() disposition to JQStore
                        // although the job might've finished, we couldn't tell JQStore, thus the loop can't be closed
                        // Therefore, we will gracefullyRetryCurrentJob() so that it can run again another day and close out the loop gracefully
                        $result = $e->getMessage();
                        $this->logJobStatus($this->currentJob, "signal raised during job->run()", true);
                        $this->gracefullyRetryCurrentJob($this->currentJob);
                        $this->currentJob = NULL;
                        // now that we've cleaned up, the run loop will gracefully exit
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
                        $s = $this->options['wakeupEvery'];
                        $ms = 0;
                        if ($this->options['enableJitter'])
                        {
                            $ms = rand(0, 999);
                        }
                        $this->log("Sleeping for {$s}.{$ms} seconds...");
                        JQWorker::sleep($s, $ms * 1000000);
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->currentSignalNo === NULL) throw $e;  // we only handle signals here

            // This block helps JQJobs minimize the incidence of jobs getting stuck in the "running" state
            // It is designed to catch jobs that have been checked out but not yet run when a signal fires
            // Therefore, we will gracefullyRetryCurrentJob() so that it can run again another day and close out the loop gracefully
            $result = $e->getMessage();
            $this->log("signal raised during run()");
            $this->gracefullyRetryCurrentJob($this->currentJob);
            exit(self::EXIT_CODE_SIGNAL);
        }

        $this->logQueueStatus("Stopping worker process.");
    }

    private function getMemoryLimitInBytes()
    {
        $val = trim(ini_get('memory_limit'));
        if ($val == -1) { $val = '512M'; }

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
        $this->currentSignalNo = $sigNo;
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

        // if we got here, the signal handler decided to eat the signal
        $this->currentSignalNo = NULL;
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

    /**
     * Only public for testing purposes....
     */
    public function gracefullyRetryCurrentJob(JQManagedJob $mJob)
    {
        if ($mJob)
        {
            $this->logJobStatus($mJob, "Gracefully re-trying current job due to signal interruption.", true);
            // don't trust $mJob; there is a race between when the job *actually* finishes and we can persist it to the JQStore...
            // during this time if there is a failure, the job is in an indeterminate state since PHP doesn't have un-interruptible blocks.
            // THUS in this case we care only if the DB thinks the job is checked out/running; if so, we just retry it.
            // Since we EXIT the script after this block, we don't have to worry about parallel universe collisions.
            // Note that to test this, we need to get the DB version of the job to know what's what.
            $this->jqStore->abort();
            $persistedVersionOfJob = NULL;
            try {
                $persistedVersionOfJob = $this->jqStore->get($mJob->getJobId());
            } catch (JQStore_JobNotFoundException $e) {
                $this->logJobStatus($mJob, "Completed job already persisted via JQStore.", true);
                return;
            }
            if ($persistedVersionOfJob && $persistedVersionOfJob->getStatus() === JQManagedJob::STATUS_RUNNING)
            {
                $this->logJobStatus($persistedVersionOfJob, "Failing job with mulligan.", true);
                $persistedVersionOfJob->markJobFailed("Worker was asked to terminate immediately.", true);
                $this->logJobStatus($persistedVersionOfJob, "Successfully failed job with mulligan.", true);
            }
         }
    }

    /**
     * Sets a flag to have the worker exit gracefully after the current job completes.
     */
    public function stop()
    {
        $this->okToRun = false;
        $this->logQueueStatus("Stop requested for worker process", true);
    }

    /**
     * An interal version of sleep that we guarantee to not interfere with signals or have trouble waking up on time.
     *
     * Some versions of sleep/usleep rely on system entropy to exit cleanly.
     *
     * On Heroku, since each app is sandboxed, there is very little entropy
     * available to each dyno, so sleep tends to not wake up in time--sometimes
     * several seconds late. 
     *
     * time_nanosleep seems to be immune from these issues.
     */
    public static function sleep($seconds, $nanoSeconds = 0)
    {
        // sanitize input
        $seconds = max(0, $seconds);
        $nanoSeconds = max(0, $nanoSeconds);
        $nanoSeconds = min(999999999, $nanoSeconds);
        time_nanosleep($seconds, $nanoSeconds);
    }
}

// NOTE: php can transform exceptions; ie PDO will catch JQWorker_SignalException and convert it to a PDOException with JQWorker_SignalException as the previous exception
// We might consider not having a special subclass for this since we can't count on it and intead really we need to just use our worker-level "currentSignalNo" flag to dectect
// whether we're in a signal handler
class JQWorker_SignalException extends Exception {}
