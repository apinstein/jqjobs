<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * A simple in-memory array-based queue store.
 *
 * Useful for testing/developing queues and also extremely simple queueing situations that run the lifetime of a single PHP process.
 */
class JQStore_Array implements JQStore
{
    protected $queue = array();
    protected $jobId = 1;
    protected $mutexInUse = false;

    public function enqueue(JQJob $job)
    {
        $existingManagedJob = $this->existsJobForCoalesceId($job->coalesceId());
        if ($existingManagedJob)
        {
            return $existingManagedJob;
        }

        $mJob = new JQManagedJob($this, $job);

        $jobId = $this->nextJobId();
        $mJob->setJobId($jobId);
        $this->queue[$jobId] = $mJob;
        $mJob->setStatus(JQManagedJob::STATUS_QUEUED);

        return $mJob;
    }

    private function nextJobId()
    {
        return $this->jobId++;
    }

    function detectHungJobs()
    {
        foreach ($this->queue as $mJob) {
            if ($mJob->isPastMaxRuntimeSeconds())
            {
                $mJob->retry(true);
            }
        }
    }

    public function existsJobForCoalesceId($coalesceId)
    {
        if ($coalesceId === NULL)
        {
            return NULL;
        }

        foreach ($this->queue as $mJob) {
            $mJobId = $mJob->coalesceId();
            if ((string) $mJobId === (string) $coalesceId)
            {
                return $mJob;
            }
        }

        return NULL;
    }

    public function next($queueName = NULL)
    {
        foreach ($this->queue as $mJob) {
            if ($mJob->getStatus() !== JQManagedJob::STATUS_QUEUED) continue;
            if (!$mJob->matchesQueueNameFilter($queueName)) continue;

            $mJob->markJobStarted();
            // no locking needed for in-process queue
            return $mJob;
        }
        return NULL;
    }
    public function count($queueName = NULL, $status = NULL)
    {
        $jobs = $this->jobs($queueName, $status);
        return count($jobs);
    }
    public function jobs($queueName = NULL, $status = NULL)
    {
        $jobs = array();
        foreach ($this->queue as $mJob) {
            if ($queueName && $mJob->getQueueName() !== $queueName) continue;
            if ($status && $mJob->getStatus() !== $status) continue;
            $jobs[] = $mJob;
        }
        return $jobs;
    }
    public function get($jobId)
    {
        if (!isset($this->queue[$jobId])) throw new JQStore_JobNotFoundException();

        return $this->queue[$jobId];
    }
    public function getWithMutex($jobId)
    {
        if ($this->mutexInUse) throw new JQStore_JobIsLockedException("JQStore_Array allows only one job checked out with a mutex.");
        $this->mutexInUse = true;
        return $this->get($jobId);
    }
    public function clearMutex($jobId)
    {
        $this->mutexInUse = false;
    }
    public function getByCoalesceId($coalesceId)
    {
        // Look for the job
        foreach ($this->queue as $mJob) {
            if ($mJob->coalesceId() == $coalesceId) return $mJob;
        }

        // We didn't find a job.
        return NULL;
    }
    public function save(JQManagedJob $job) {} // noop -- in memory!
    public function delete(JQManagedJob $mJob)
    {
        unset($this->queue[$mJob->getJobId()]);
    }
    public function statusDidChange(JQManagedJob $mJob, $oldStatus, $message)
    {
        //print "JQStore [Job {$mJob->getJobId()}] {$oldStatus} ==> {$mJob->getStatus()} {$message}\n";
    }
    public function abort() {}
}
