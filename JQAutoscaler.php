<?php

/**
 * An autoscaler for JQJobs.
 *
 * Works with JQScalable allow scaling up of arbitrary cloud infrastructure to handle JQJobs load.
 *
 * Usage:
 * - For JQStore_Propel, just pass in autoscalingConfig and that's it!
 *
 * Dev:
 * - Whenever you change the status of a job, do so in a JQAutoscaler->wrapThingAffectingQueuedJobCount() wrapper.
 * - Configure your scaling params for each queue you want to be auto-scaled
 * - A special queue named "autoscaler" is reserved for a worker to run this process.
 *
 * @see JQScalable
 */
class JQAutoscaler
{
    protected $jqStore;
    protected $scalable;
    protected $config;
    // @todo should this be moved to per-queue? probably yes
    protected $minSecondsToProcessDownScale = 5;   // app config; do we want to throttle downscaling for any reason?
    // @todo should this be moved to per-queue? probably yes
    protected $scaleDownMinJump = 20;

    protected $lastDetectHungJobsAt;
    protected $detectHungJobsInterval;

    public function __construct($jqStore, $scalable, $config)
    {
        $this->jqStore = $jqStore;
        $this->lastDetectHungJobsAt = time();
        $this->detectHungJobsInterval = 10;

        // @todo config.maxConcurrency should be in the JQScalable interface, too
        $this->config = $config;
        $this->scalable = $scalable;

        $this->minSecondsToProcessDownScale = max($this->minSecondsToProcessDownScale, $this->scalable->minSecondsToProcessDownScale());
    }

    public function countPendingJobs($onlyThisQueue = NULL)
    {
        $numPendingJobs = 0;
        foreach ($this->config as $queueName => $queueConfig) {
            if ($onlyThisQueue && $onlyThisQueue != $queueName) continue;

            $numPendingJobs += $this->jqStore->count($queueName, JQManagedJob::STATUS_QUEUED);
            $numPendingJobs += $this->jqStore->count($queueName, JQManagedJob::STATUS_RUNNING);
        }
        return $numPendingJobs;
    }

    /**
     * Any work that could increase the number of queued jobs should be called thru here so that the autoscaler process can be kicked off whenever there is work to do
     *
     * @param callable A function that does the work which might affect the number of queued jobs.
     */
    public function wrapThingAffectingQueuedJobCount($f)
    {
        $countBefore = $this->countPendingJobs();
        $f();
        if ($countBefore === 0 || rand(0, 50) == 0)
        {
            print "Turning on autoscaler worker...\n";
            $this->scalable->setCurrentWorkersForQueue(1, JQScalable::WORKER_AUTOSCALER);
        }
    }

    protected $scalingHistory = array();
    private function performAutoscaleChange($queueName, $from, $to)
    {
        if (!isset($this->scalingHistory[$queueName]))
        {
            $this->scalingHistory[$queueName] = array(
                'lastScaleDts'   => 0,
                'lastScale'      => $from,
                'lastScaleDelta' => $to,
            );
        }

        $okToScale = true;
        $msg = NULL;
        if ($to === $from)
        {
            $okToScale = false;
            $msg = "No change";
        }
        // scaling up
        else if ($to > $from)
        {
            if (
                $this->scalingHistory[$queueName]['lastScaleDelta'] < 0                                                         // last scale was DOWN
                AND (time() - $this->scalingHistory[$queueName]['lastScaleDts']) < $this->minSecondsToProcessDownScale          // AND hasn't had a chance to "finalize"
               )
            {
                $okToScale = false;
                $msg = "There is a pending scale-down operation: {$this->minSecondsToProcessDownScale} seconds required...";
            }
            else
            {
                $msg = "Scaling up immediately.";
            }
        }
        // scaling down
        else if ($to < $from && $to === 0)  { $msg = "Scaling to 0 immediately"; }
        else if ($to < $from)
        {
            if (
                $this->scalingHistory[$queueName]['lastScaleDelta'] < 0                                                         // last scale was DOWN
                AND (time() - $this->scalingHistory[$queueName]['lastScaleDts']) < $this->minSecondsToProcessDownScale          // AND hasn't had a chance to "finalize"
               )
            {
                $okToScale = false;
                $msg = "There is a pending scale-down operation: {$this->minSecondsToProcessDownScale} seconds required...";
            }
            if ($from - $to < $this->scaleDownMinJump)
            {
                $okToScale = false;
                $msg = "We don't scale down unless drop > {$this->scaleDownMinJump} workers";
            }
        }
        else throw new Exception("BAD MATH!");

        print "[" . str_pad($queueName, 20) . "]: " . ($okToScale ? 'WILL' : 'WONT') . " " . str_pad($from, 3) . " => " . str_pad($to, 3) . " " . ($msg ? $msg : NULL) . "\n";
        if (!$okToScale) return;

        $this->scalable->setCurrentWorkersForQueue($to, $queueName);
        $this->scalingHistory[$queueName]['lastScaleDts'] = time();
        $this->scalingHistory[$queueName]['lastScale'] = $to;
        $this->scalingHistory[$queueName]['lastScaleDelta'] = $to - $from;
    }

    protected function detectHungJobs()
    {
        if (time() - $this->lastDetectHungJobsAt <= $this->detectHungJobsInterval) return;

        print "JQAutoscaler::detectHungJobs() running...";
        $this->jqStore->detectHungJobs();
        $this->lastDetectHungJobsAt = time();
        print " done!\n";
    }

    public function run()
    {
        while (true) {
            $workersPerQueue = array();

            foreach ($this->config as $queue => $queueConfig) {
                $numPendingJobs = $this->countPendingJobs($queue);

                switch ($queueConfig['scalingAlgorithm']) {
                    case 'linear':
                        $numDesiredWorkers = $numPendingJobs;
                        break;
                    default:
                        throw new Exception("unknown scaling algorithm: {$queueConfig['scalingAlgorithm']}");
                }
                $numDesiredWorkers = min($numDesiredWorkers, $queueConfig['maxConcurrency']);

                $numCurrentWorkers = $this->scalable->countCurrentWorkersForQueue($queue);
                $this->performAutoscaleChange($queue, $numCurrentWorkers, $numDesiredWorkers);
                //print "{$queue}: {$numCurrentWorkers} => {$numDesiredWorkers}\n";
                //$this->scalable->setCurrentWorkersForQueue($numDesiredWorkers, $queue);
                $workersPerQueue[$queue] = $numDesiredWorkers;
            }
            print "\n";

            if (array_sum($workersPerQueue) === 0)
            {
                foreach ($this->config as $queue => $queueConfig) {
                    $this->scalable->setCurrentWorkersForQueue(0, $queue);
                }
                $this->scalable->setCurrentWorkersForQueue(0, JQScalable::WORKER_AUTOSCALER);
                break;
            }

            $this->detectHungJobs();

            JQWorker::sleep(1);
        }
        print "Autoscaler exiting.\n";
    }
}
