<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * A persistent queue store, stored in a DB via a Propel class.
 *
 * The propel class should have all fields coded in JQManagedJob::persistableFields().
 *
 * This queue is suitable for moderate to heavy workloads (dozens to hundreds of jobs per second) and supports concurrent access from multiple workers.
 */
class JQStore_Propel implements JQStore
{
    protected $con;
    protected $propelClassName;
    protected $options;
    protected $mutexInUse;

    /**
     * JQStore/Propel driver constructor.
     *
     * @param string Propel Object/Class name
     * @param object PropelPDO
     * @param array  OPTIONS:
     *                  ...
     */
    public function __construct($propelClassName, $con, $options = array())
    {
        $this->propelClassName = $propelClassName;
        $this->con = $con;
        $this->mutexInUse = false;

        $this->options = array_merge(
            array(
                'tableName'                   => 'JQStoreManagedJob',
                'jobIdColName'                => 'JOB_ID',
                'jobCoalesceIdColName'        => 'COALESCE_ID',
                'jobQueueNameColName'         => 'QUEUE_NAME',
                'jobStatusColName'            => 'STATUS',
                'jobPriorityColName'          => 'PRIORITY',
                'jobStartDtsColName'          => 'START_DTS',
                'jobEndDtsColName'            => 'END_DTS',
                'jobMaxRuntimeSecondsColName' => 'MAX_RUNTIME_SECONDS',
                'toArrayOptions'              => array('dtsFormat' => 'r'),
            ),
            $options
        );
        // eval propel constants, thanks php 5.2 :( On 5.3 I think we can do $this->propelClassName::TABLE_NAME etc...
        $this->options['tableName'] = eval("return {$this->propelClassName}Peer::TABLE_NAME;");
        foreach (array('jobIdColName', 'jobQueueNameColName', 'jobStatusColName', 'jobPriorityColName', 'jobStartDtsColName', 'jobEndDtsColName') as $colName) {
            $this->options[$colName] = eval("return {$this->propelClassName}Peer::{$this->options[$colName]};");
        }
    }

    public function enqueue(JQJob $job)
    {
        $mJob = NULL;

        $this->con->beginTransaction();
        try {
            // look for coalesceId collision
            $coalesceId = $job->coalesceId();
            $mJob = $this->existsJobForCoalesceId($job->coalesceId());

            if (!$mJob)
            {
                // create a new job
                $mJob = new JQManagedJob($this, $job);

                $mJob->setStatus(JQManagedJob::STATUS_QUEUED);
                $dbJob = new $this->propelClassName;
                $dbJob->fromArray($mJob->toArray($this->options['toArrayOptions']), BasePeer::TYPE_STUDLYPHPNAME);
                $dbJob->save($this->con);

                $mJob->setJobId($dbJob->getJobId());
            }

            $this->con->commit();
        } catch (Exception $e) {
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
            // @todo This is a bit ugly; we probably need something like a JQStore::performWithMutex($jobId, $f) where we can wrap f internally in a try/catch to do this more cleanly
            $this->con->beginTransaction();
            try {
                $mJob = $this->getWithMutex($possiblyHungJob->getJobId());

                if ($mJob->isPastMaxRuntimeSeconds())   // verify with the JQJobs calc
                {
                    $mJob->retry(true);
                }
                $this->clearMutex($mJob->getJobId());
                $this->con->commit();
            } catch (Exception $e) {
                $this->abort();
                throw $e;
            }
        }
    }

    public function existsJobForCoalesceId($coalesceId)
    {
        if ($coalesceId === NULL)
        {
            return NULL;
        }
        else
        {
            // @todo is this lock really necessary? if the contract of JQJobs is that it guarantees jobs run at least once, then
            // is it even necessary to ever lock for this? the risk is only that the existing job would be dequeued while a coalesce was pending.
            // worst case seems that we should be using a select ... for update here? certainly that would minimize the surface are of the mutex for performance reasons.
            //
            // OPTIMIZATION: only lock the table when job being enqueued has a coalesceId; otherwise inserts do not need to be exclusive with other activity (deletes, updates, etc)

            // lock the table so we can be sure to get mutex to safely enqueue job without risk of having a colliding coalesceId.
            // EXCLUSIVE mode is used b/c it's the most exclusive mode that doesn't conflict with pg_dump (which uses ACCESS SHARE)
            // see http://stackoverflow.com/questions/6507475/job-queue-as-sql-table-with-multiple-consumers-postgresql/6702355#6702355
            // theoretically this lock should prevent the unique index from ever tripping.
            $lockSql = "lock table {$this->options['tableName']} in EXCLUSIVE mode;";
            $this->con->query($lockSql);

            return $this->getByCoalesceId($coalesceId);
        }
    }

    public function next($queueName = NULL)
    {
        $nextMJob = NULL;

        $this->con->beginTransaction();
        try {
            // find "next" job
            $selectColumnsForPropelHydrate = join(',', call_user_func(array("{$this->propelClassName}Peer", 'getFieldNames'), BasePeer::TYPE_COLNAME));
            // options is trusted w/r/t sql-injection
            $sql = "select
                        {$selectColumnsForPropelHydrate}
                        from {$this->options['tableName']}
                        where
                            {$this->options['jobStatusColName']}  = '" . JQManagedJob::STATUS_QUEUED . "'
                            AND ({$this->options['jobStartDtsColName']} IS NULL OR {$this->options['jobStartDtsColName']} < now())
                            " . ($queueName ? "AND {$this->options['jobQueueNameColName']} = '" . pg_escape_string($queueName) . "'" : NULL) . "
                        order by
                            {$this->options['jobPriorityColName']} desc,
                            coalesce(now(), {$this->options['jobStartDtsColName']}) asc,
                            {$this->options['jobIdColName']} asc
                        limit 1
                    for update
                ";
            $stmt = $this->con->query($sql);
            if ($stmt->rowCount() === 1)
            {
                $dbJobRow = $stmt->fetch();
                $dbJob = new $this->propelClassName;
                $dbJob->hydrate($dbJobRow);

                $nextMJob = new JQManagedJob($this);
                $nextMJob->fromArray($dbJob->toArray(BasePeer::TYPE_STUDLYPHPNAME));
                $nextMJob->markJobStarted();
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

    /**
     * @param string The ID of the job to get.
     * @throws JQStore_JobIsLockedException
     */
    private function getDbJob($jobId)
    {
        // always load job from DB... due to the crazy re-entrancy issues due to signal handling, it's best to never trust the Propel cache.
        call_user_func(array("{$this->propelClassName}Peer", 'clearInstancePool'));

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

        // lock it!
        $stmt = $this->con->query($sql);
        $this->mutexInUse = true;

        if ($stmt->rowCount() !== 1)
        {
            $this->clearMutex($jobId);
            throw new JQStore_JobNotFoundException();
        }

        // fetch job
        return $this->get($jobId);
    }

    public function clearMutex($jobId)
    {
        if (!$this->mutexInUse) return; // nothing to do!

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
        $dbJob->save($this->con);
    }

    public function delete(JQManagedJob $mJob)
    {
        $dbJob = $this->getDbJob($mJob->getJobId());
        $dbJob->delete($this->con);
    }

    public function abort()
    {
        while ($this->con->isInTransaction())
        {
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
