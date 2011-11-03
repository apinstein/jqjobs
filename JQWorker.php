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
    protected $jqStore;
    protected $options;
    protected $okToRun = true;
    protected $pid = NULL;
    protected $jobsProcessed = 0;
    protected $allIncludedFiles = NULL;

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
     */
    public function __construct($jqStore, $options = array())
    {
        $this->jqStore = $jqStore;
        $this->options = array_merge(array(
                                            'queueName'             => NULL,
                                            'wakeupEvery'           => 5,
                                            'verbose'               => false,
                                            'silent'                => false,
                                            'guaranteeMemoryForJob' => 0.2 * $this->getMemoryLimitInBytes(),
                                            'exitIfNoJobs'          => false,
                                            'exitAfterNJobs'        => NULL,
                                          ),
                                     $options
                                    );
        $this->pid = getmypid();
    }

    protected function log($msg, $verboseOnly = false)
    {
        if ($this->options['silent']) return;
        if ($verboseOnly and !$this->options['verbose']) return;
        print "[{$this->pid}] {$msg}\n";
    }

    public function jobsProcessed()
    {
        return $this->jobsProcessed;
    }


    /**
     * Starts the worker process.
     *
     * Blocks until the worker exists.
     */
    public function start()
    {
        $this->log("Starting worker process on queue: " . ($this->options['queueName'] === NULL ? '(any)' : $this->options['queueName']));;

        // install signal handlers if possible
        declare(ticks = 1);
        if (function_exists('pcntl_signal'))
        {
            foreach (array(SIGHUP, SIGINT, SIGQUIT, SIGABRT, SIGTERM) as $signal) {
                pcntl_signal($signal, array($this, 'stop'));
            }
        }
        while ($this->okToRun) {
            $this->memCheck();
            $this->codeCheck();

            $nextJob = $this->jqStore->next($this->options['queueName']);
            if ($nextJob)
            {
                $this->log("[Job: {$nextJob->getJobId()} {$nextJob->getStatus()}] {$nextJob->getJob()->description()}", true);
                $result = $nextJob->run($nextJob);
                if ($result === NULL)
                {
                    $this->log("[Job: {$nextJob->getJobId()} {$nextJob->getStatus()}]", true);
                }
                else
                {
                    $this->log("[Job: {$nextJob->getJobId()} {$nextJob->getStatus()}] {$result}");
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
                    sleep($this->options['wakeupEvery']);
                }
            }
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
            exit(1);
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
        foreach ($this->allIncludedFiles as $includedFile => $dts) {
            if ($dts != filemtime($includedFile))
            {
                $this->log("JQWorker exiting since we detected updated code in {$includedFile}.");
                exit(1);
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
        return true;
    }
}

