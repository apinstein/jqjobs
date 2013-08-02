<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * A persistent queue store, stored in a DB via a Propel class.
 *
 * The propel class should have all fields coded in JQManagedJob::persistableFields().
 *
 * This queue is suitable for moderate to heavy workloads (dozens to hundreds of jobs per second) and supports concurrent access from multiple workers.
 * This queue supports JQAutoscaler.
 */
class JQStore_Propel implements JQStore, JQStore_Autoscalable
{
    protected $con;
    protected $propelClassName;
    protected $options;
    protected $mutexInUse;
    protected $autoscaler;

    /**
     * JQStore/Propel driver constructor.
     *
     * @param string Propel Object/Class name
     * @param object PropelPDO
     * @param array  OPTIONS:
     *                  nextAlwaysWaitsForLock: If true, the next() call will wait for a lock on the DB rather than abort the dequeue attempt if the database is busy. DEFAULT: true
     *                                          When true, you can always count on a worker getting a job if there is one. When false, it's possible that the worker is not able
     *                                          to dequeue a job due to database load.
     *                  ...
     */
    public function __construct($propelClassName, $con, $options = array())
    {
        $this->propelClassName = $propelClassName;
        $this->con = $con;
        $this->mutexInUse = false;
        $this->autoscaler = NULL;

        $this->options = array_merge(array(
                                            'nextAlwaysWaitsForLock'        => true,
                                            'tableName'                     => 'JQStoreManagedJob',
                                            'jobIdColName'                  => 'JOB_ID',
                                            'jobCoalesceIdColName'          => 'COALESCE_ID',
                                            'jobQueueNameColName'           => 'QUEUE_NAME',
                                            'jobStatusColName'              => 'STATUS',
                                            'jobPriorityColName'            => 'PRIORITY',
                                            'jobStartDtsColName'            => 'START_DTS',
                                            'jobEndDtsColName'              => 'END_DTS',
                                            'jobMaxRuntimeSecondsColName'   => 'MAX_RUNTIME_SECONDS',
                                            'toArrayOptions'                => array('dtsFormat' => 'r'),
                                          ),
                                     $options
                                    );
        // eval propel constants, thanks php 5.2 :( On 5.3 I think we can do $this->propelClassName::TABLE_NAME etc...
        $this->options['tableName'] = eval("return {$this->propelClassName}Peer::TABLE_NAME;");
        foreach (array('jobIdColName', 'jobQueueNameColName', 'jobStatusColName', 'jobPriorityColName', 'jobStartDtsColName', 'jobEndDtsColName') as $colName) {
            $this->options[$colName] = eval("return {$this->propelClassName}Peer::{$this->options[$colName]};");
        }

        // bad things happen without this!
        Propel::disableInstancePooling();
    }

    public function setAutoscaler(JQAutoscaler $as)
    {
        $this->autoscaler = $as;
    }

    public function runAutoscaler()
    {
        if (!$this->autoscaler) throw new Exception("No autoscaler configured.");
        $this->autoscaler->run();
    }

    public function enqueue(JQJob $job, $options = array())
    {
        $mJob = NULL;
 
        $this->con->beginTransaction();
        try {
            $coalesceId = $job->coalesceId();
            if ($coalesceId !== NULL)
            {
                // OPTIMIZATION: only lock the table when job being enqueued has a coalesceId; otherwise inserts do not need to be exclusive with other activity (deletes, updates, etc)

                // lock the table so we can be sure to get mutex to safely enqueue job without risk of having a colliding coalesceId.
                // EXCLUSIVE mode is used b/c it's the most exclusive mode that doesn't conflict with pg_dump (which uses ACCESS SHARE)
                // see http://stackoverflow.com/questions/6507475/job-queue-as-sql-table-with-multiple-consumers-postgresql/6702355#6702355
                // theoretically this lock should prevent the unique index from ever tripping.
                $lockSql = "lock table {$this->options['tableName']} in EXCLUSIVE mode;";
                $this->con->query($lockSql);

                // look for coalesceId collision
                $mJob = $this->existsJobForCoalesceId($job->coalesceId());
            }
 
            if (!$mJob)
            {
                // create a new job
                $mJob = new JQManagedJob($this, $options);
                $mJob->setJob($job);
                $mJob->setStatus(JQManagedJob::STATUS_QUEUED);
                $mJob->setCoalesceId($job->coalesceId());
                
                $dbJob = new $this->propelClassName;
                $dbJob->fromArray($mJob->toArray($this->options['toArrayOptions']), BasePeer::TYPE_STUDLYPHPNAME);
                $this->saveDBJob($dbJob, $this->con);
 
                $mJob->setJobId($dbJob->getJobId());
            }
 
            $this->con->commit();
        } catch (PropelException $e) {
            $this->con->rollback();
            throw $e;
        }

        return $mJob;
    }
    
    function detectHungJobs()
    {
        // optimized query to select only possibly hung jobs...
        $c = new Criteria;
        $c->add($this->options['jobStatusColName'], JQManagedJob::STATUS_RUNNING);
        $c->add($this->options['jobMaxRuntimeSecondsColName'], NULL, Criteria::ISNOTNULL);
        $c->add($this->options['jobMaxRuntimeSecondsColName'], "{$this->options['jobStartDtsColName']} + ({$this->options['jobMaxRuntimeSecondsColName']}||' seconds')::interval < now()", Criteria::CUSTOM);
        $possiblyHungJobs = call_user_func(array("{$this->propelClassName}Peer", 'doSelect'), $c, $this->con);
        foreach ($possiblyHungJobs as $possiblyHungJob) {
            $mJob = $this->getWithMutex($possiblyHungJob->getJobId());
            if ($mJob->isPastMaxRuntimeSeconds())   // verify with the JQJobs calc
            {
                $mJob->retry(true);
            }
            $this->clearMutex($mJob->getJobId());
        }
    }

    public function existsJobForCoalesceId($coalesceId)
    {
        if ($coalesceId === NULL)
        {
            return NULL;
        }

        return $this->getByCoalesceId($coalesceId);
    }

    const ADVISORY_LOCK_ID = 100000;
    public function next($queueName = NULL)
    {
        $nextMJob = NULL;

        $this->con->beginTransaction();
        try {
            // lock the table so we can be sure to get mutex access to "next" job
            // EXCLUSIVE mode is used b/c it's the most exclusive mode that doesn't conflict with pg_dump (which uses ACCESS SHARE)
            // see http://stackoverflow.com/questions/6507475/job-queue-as-sql-table-with-multiple-consumers-postgresql/6702355#6702355
            $nowait = ($this->options['nextAlwaysWaitsForLock'] ? '' : 'NOWAIT');
            //$lockSql = "lock table {$this->options['tableName']} in EXCLUSIVE mode {$nowait};";
            $lockSql = "select pg_advisory_lock(" . self::ADVISORY_LOCK_ID . ");";
            $stmt = $this->con->query($lockSql);
        } catch (PDOException $e) {
            $this->con->rollback();
            return $nextMJob;
        }

        try {
            // find "next" job
            $c = new Criteria;
            $c->add($this->options['jobStatusColName'], JQManagedJob::STATUS_QUEUED);
            $c->add($this->options['jobStartDtsColName'], "({$this->options['jobStartDtsColName']} is null OR {$this->options['jobStartDtsColName']} < now())", Criteria::CUSTOM);
            $c->addDescendingOrderByColumn($this->options['jobPriorityColName']);
            $c->addAscendingOrderByColumn("coalesce(now(), {$this->options['jobStartDtsColName']})");    // jobs with no start date should be treated as "start now"
            $c->addAscendingOrderByColumn($this->options['jobIdColName']);
            if ($queueName)
            {
                $c->add($this->options['jobQueueNameColName'], $queueName);
            }
            $dbJob = call_user_func(array("{$this->propelClassName}Peer", 'doSelectOne'), $c, $this->con);

            if ($dbJob)
            {
                $nextMJob = new JQManagedJob($this);
                $nextMJob->fromArray($dbJob->toArray(BasePeer::TYPE_STUDLYPHPNAME));
                $nextMJob->markJobStarted();
                if (!($nextMJob->getJob() instanceof JQJob))
                {
                    $nextMJob->markJobFailed("JQManagedJob.job is not a JQJob instance.");
                    $nextMJob = NULL;
                }
            }
            $this->con->commit();
        } catch (Exception $e) {
            $this->con->rollback();
            throw $e;
        }

        return $nextMJob;
    }

    public function count($queueName = NULL, $status = NULL)
    {
        $c = new Criteria;
        if ($queueName)
        {
            $c->add($this->options['jobQueueNameColName'], $queueName);
        }
        if ($status)
        {
            $c->add($this->options['jobStatusColName'], $status);
        }
        return call_user_func(array("{$this->propelClassName}Peer", 'doCount'), $c, false, $this->con);
    }

    public function jobs($queueName = NULL, $status = NULL)
    {
        $c = new Criteria;
        if ($queueName)
        {
            $c->add($this->options['jobQueueNameColName'], $queueName);
        }
        if ($status)
        {
            $c->add($this->options['jobStatusColName'], $status);
        }
        return call_user_func(array("{$this->propelClassName}Peer", 'doSelect'), $c, $this->con);
    }

    private function getDbJob($jobId)
    {
        $dbJob = call_user_func(array("{$this->propelClassName}Peer", 'retrieveByPK'), $jobId, $this->con);
        if (!$dbJob) throw new JQStore_JobNotFoundException("Couldn't find jobId {$jobId} in database.");
        return $dbJob;
    }

    private function getDbJobByCoalesceId($coalesceId)
    {
        $c = new Criteria;
        $c->add($this->options['jobCoalesceIdColName'], $coalesceId);
        return call_user_func_array(
            array("{$this->propelClassName}Peer", 'doSelectOne'),
            array($c, $this->con)
        );
    }

    /**
     * Get a JQManagedJob from the propel store corresponding to the given job id.
     *
     * This is basically an unseralizer.
     */
    public function get($jobId)
    {
        $dbJob = $this->getDbJob($jobId);
        return $this->getJQManagedJobForDbJob($dbJob);
    }

    public function getWithMutex($jobId)
    {
        if ($this->mutexInUse) throw new JQStore_JobIsLockedException("JQStore_Propel allows only one job checked out with a mutex per process.");

        // lock
        $jobId = (int) $jobId;  // sql injection preventer
        $sql = "select {$this->options['jobIdColName']} from {$this->options['tableName']} where {$this->options['jobIdColName']} = {$jobId} for update";
        $this->con->beginTransaction();
        $this->con->query($sql);

        $this->mutexInUse = true;

        // fetch job
        return $this->get($jobId);
    }

    public function clearMutex($jobId)
    {
        if (!$this->mutexInUse) throw new Exception("No mutex.");
        $this->con->commit();
        $this->mutexInUse = false;
    }

    /**
     * Get a JQManagedJob from the propel store corresponding to the given coalesce id.
     *
     * This is basically an unseralizer.
     */
    public function getByCoalesceId($coalesceId)
    {
        $dbJob = $this->getDbJobByCoalesceId($coalesceId);
        if ($dbJob === NULL) return NULL;
        return $this->getJQManagedJobForDbJob($dbJob);
    }

    private function getJQManagedJobForDbJob($dbJob)
    {
        $mJob = new JQManagedJob($this);
        $mJob->fromArray($dbJob->toArray(BasePeer::TYPE_STUDLYPHPNAME));
        return $mJob;
    }

    /**
     * Update our persisted JQManagedJob (which is a propel class) into the DB
     *
     * This is basically a serializer.
     */
    public function save(JQManagedJob $mJob)
    {
        $dbJob = $this->getDbJob($mJob->getJobId());
        $dbJob->fromArray($mJob->toArray($this->options['toArrayOptions']), BasePeer::TYPE_STUDLYPHPNAME);
        $this->saveDBJob($dbJob, $this->con);
    }

    /**
     * Should be used to call the Propel save() function *always* so that we can integrate with autoscaler correctly.
     */
    private function saveDBJob($dbJob, $con)
    {
        $saverF = function() use ($dbJob, $con) {
            $dbJob->save($con);
        };

        // @todo $dbJob->getStatus() should use Propel map functions to use $this->options['jobStatusColName']
        if ($this->autoscaler && $dbJob->getStatus() === JQManagedJob::STATUS_QUEUED)
        {
            $this->autoscaler->wrapThingAffectingQueuedJobCount($saverF);
        }
        else
        {
            $saverF();
        }
    }

    public function delete(JQManagedJob $mJob)
    {
        $dbJob = $this->getDbJob($mJob->getJobId());
        $dbJob->delete($this->con);
    }

    public function abort()
    {
        // delete this while() block once we are comfortable empiricaly that this is true
        while ($this->con->isInTransaction())
        {
            throw new Exception("abort() called while transaction in progress, shouldn't happen anymore.");
            $this->con->rollback();
        }
    }

    /**
     * Status changed hook.
     *
     * No action by default; subclass and override if you want JQStore-wide logging, etc.
     */
    public function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
}
