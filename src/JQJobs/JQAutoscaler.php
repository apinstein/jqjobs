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
    protected $exitIfNoJobs = false;

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
            #print "Turning on autoscaler worker...\n";
            $this->scalable->setCurrentWorkersForQueue(1, JQScalable::WORKER_AUTOSCALER);
        }
    }

    protected $scalingHistory = array();
    private function performAutoscaleChange($queueName, $from, $to)
    {
        $queueScalingHistory = $this->scalingHistory[$queueName];
        if (!isset($queueScalingHistory))
        {
            $queueScalingHistory = array(
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
                $queueScalingHistory['lastScaleDelta'] < 0                                                         // last scale was DOWN
                AND (time() - $queueScalingHistory['lastScaleDts']) < $this->minSecondsToProcessDownScale          // AND hasn't had a chance to "finalize"
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
                $queueScalingHistory['lastScaleDelta'] < 0                                                         // last scale was DOWN
                AND (time() - $queueScalingHistory['lastScaleDts']) < $this->minSecondsToProcessDownScale          // AND hasn't had a chance to "finalize"
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
        $queueScalingHistory['lastScaleDts'] = time();
        $queueScalingHistory['lastScale'] = $to;
        $queueScalingHistory['lastScaleDelta'] = $to - $from;
    }

    protected function detectHungJobs()
    {
        if (time() - $this->lastDetectHungJobsAt <= $this->detectHungJobsInterval) return;

        print "JQAutoscaler::detectHungJobs() running...";
        $this->jqStore->detectHungJobs();
        $this->lastDetectHungJobsAt = time();
        print " done!\n";
    }

    public static function calculateScale($scalingAlgorithm, $numPendingJobs, $maxConcurrency)
    {
        switch ($scalingAlgorithm) {
            case 'halfLinear':
                $numDesiredWorkers = $numPendingJobs / 2;
                break;
            case 'linear':
                $numDesiredWorkers = $numPendingJobs;
                break;
            default:
                throw new Exception("unknown scaling algorithm: {$scalingAlgorithm}");
        }

        // round down
        $numDesiredWorkers = (int) $numDesiredWorkers;

        // max concurrency
        $numDesiredWorkers = min($numDesiredWorkers, $maxConcurrency);
        
        // min of 1 if there's 1 job
        if ($numPendingJobs >= 1)
        {
            $numDesiredWorkers = max(1, $numDesiredWorkers);
        }

        return $numDesiredWorkers;
    }

    public function run()
    {
        $isHibernating = false;
        while (true) {
            $workersPerQueue = array();

            foreach ($this->config as $queue => $queueConfig) {
                try
                {
                    $numPendingJobs = $this->countPendingJobs($queue);
                    $numDesiredWorkers = self::calculateScale($queueConfig['scalingAlgorithm'], $numPendingJobs, $queueConfig['maxConcurrency']);
                    $numCurrentWorkers = $this->scalable->countCurrentWorkersForQueue($queue);
                    $this->performAutoscaleChange($queue, $numCurrentWorkers, $numDesiredWorkers);
                    $workersPerQueue[$queue] = $numDesiredWorkers;
                }
                catch(Exception $e)
                {
                    print "Error trying to scale the '{$queue}' queue: ";
                    print $e->getMessage();
                }
            }
            print "\n";

            if (array_sum($workersPerQueue) === 0)
            {
                if (!$isHibernating)
                {
                    // @todo -- seems like this is not necessary; isn't it accomplished by the performAutoscaleChange() above?
                    // maybe doing these 2 calls so close is what's causing our oddly unclean exits
                    foreach ($this->config as $queue => $queueConfig) {
                        $this->scalable->setCurrentWorkersForQueue(0, $queue);
                    }

                    if ($this->exitIfNoJobs)
                    {
                        $this->scalable->setCurrentWorkersForQueue(0, JQScalable::WORKER_AUTOSCALER);
                        break;
                    }

                    print "Entering hibernation....\n";
                    $isHibernating = true;
                }
            }
            else
            {
                if ($isHibernating) print "Exiting hibernation..,\n";
                $isHibernating = false;

                $this->detectHungJobs();
            }

            JQWorker::sleep(15);
        }
        print "Autoscaler exiting.\n";
    }
}
