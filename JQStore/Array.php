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

    public function enqueue(JQJob $job, $options = array())
    {
        if (!is_null($job->coalesceId()))
        {
            $existingManagedJob = $this->existsJobForCoalesceId($job->coalesceId());
            if ($existingManagedJob)
            {
                return $existingManagedJob;
            }
        }

        $mJob = new JQManagedJob($this, $options);
        $mJob->setJob($job);
        $mJob->setCoalesceId($job->coalesceId());
        $mJob->setJobId($this->jobId);
        $this->queue[$this->jobId] = $mJob;
        $mJob->setStatus(JQManagedJob::STATUS_QUEUED);
        $this->jobId++;
        return $mJob;
    }

    public function existsJobForCoalesceId($coalesceId)
    {
        if ($coalesceId === NULL)
        {
            return NULL;
        }

        foreach ($this->queue as $mJob) {
            $mJobId = $mJob->coalesceId();
            if ((string) $mJob->coalesceId() === (string) $coalesceId) return $mJob;
        }

        return NULL;
    }

    public function next($queueName = NULL, $minWorkFactor = NULL, $maxWorkFactor = NULL)
    {
        foreach ($this->queue as $dbJob) {
            if ($dbJob->getStatus() === JQManagedJob::STATUS_QUEUED && $dbJob->getQueueName() === $queueName && $dbJob->isAcceptableWorkFactor($minWorkFactor, $maxWorkFactor))
            {
                $dbJob->markJobStarted();
                // no locking needed for in-process queue
                return $dbJob;
            }
        }
        return NULL;
    }
    public function count($queueName = NULL, $status = NULL)
    {
        $count = 0;
        foreach ($this->queue as $dbJob) {
            if ($queueName && $dbJob->getQueueName() !== $queueName) continue;
            if ($status && $dbJob->getStatus() !== $status) continue;
            $count++;
        }
        return $count;
    }
    public function get($jobId)
    {
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
        if (!$this->mutexInUse) throw new Exception("No mutex.");
        $this->mutexInUse = false;
    }
    public function getByCoalesceId($coalesceId)
    {
        // Look for the job
        foreach ($this->queue as $dbJob) {
            if ($dbJob->coalesceId() == $coalesceId) return $dbJob;
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
}
