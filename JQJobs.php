<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * JQJobs is a job queue infrastructure for PHP.
 *
 * The job system has only a few parts:
 * - {@link JQJob} is an interface for a class that does actual work.
 * - {@link JQManagedJob} is a wrapper for JQJob's which contains metadata used to manage the job (status, priority, etc).
 * - {@link JQStore} is where JQManagedJob's are persisted. The application queues jobs in a JQStore for later processing.
 * - {@link JQWorker} runs jobs from the queue. It is typically run in a background process.
 *
 * The JQStore manages the queue and persistence of the JQManagedJob's. 
 *
 * JQStore is an interface, but the job system ships with several concrete implementations. The system is architected
 * in this manner to allow the job store to be migrated to different backing stores (memcache, db, Amazon SQS, etc).
 * JQStore implementations are very simple.
 *
 * Jobs that complete successfully are removed from the queue immediately. Jobs that fail are retried until maxAttempts is reached, and then they are marked FAILED and
 * left in the queue. It's up to the application to cleanup failed entries.
 *
 * @todo Jobs that are stuck running for longer than maxExecutionTime will be retried or failed as appropriate. Not sure whose responsibility this should be; server failure or job failure?
 *
 * If the application requires an audit log or archive of job history, it should implement this in run()/cleanup() for each job, or in a custom JQStore subclass.
 *
 * // The minimal amount of work needed to use a JQJobs is 1) create at least one job; 2) create a queuestore; 3) add jobs; 4) start a worker.
 * // 1) Create a job
 * class SampleJob implements JQJob
 * {
 *     function __construct($info) { $this->info = $info; }
 *     function run() { print $this->description() . "\n"; } // no-op
 *     function cleanup() { print "cleanup() {$this->description()}\n"; }
 *     function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) { print "SampleJob [Job {$mJob->getJobId()}] {$oldStatus} ==> {$mJob->getStatus()} {$message}\n"; }
 *     function description() { return "Sample job {$this->info}"; }
 * }
 * 
 * // 2) create a queuestore
 * $q = new JQStore_Array();
 *
 * // alternatively; create a db-based queue with Propel:
 * $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME);
 * $q = new JQStore_Propel('JQStoreManagedJob', $con);
 * 
 * // 3) Add jobs
 * foreach (range(1,10) as $i) {
 *     $q->enqueue(new SampleJob($i));
 * }
 * 
 * // 4) Start a worker to run the jobs.
 * $w = new JQWorker($q);
 * $w->start();
 *
 * @package JQJobs
 */

/**
 * A Job interface.
 *
 * Any object that needs to perform work on an asynchronous basis can do so by implementing the JQJob interface.
 *
 * The inteface is very simple, consisting only of a run() method to perform the work, and a description() method for reporting/logging purposes.
 *
 * IMPORTANT: Objects implementing JQJob will be serialized into the JQManagedJob during persistence, so it's important that they can be safely seriazlied.
 */
interface JQJob
{
    /**
     * Run the job.
     *
     * @return mixed NULL if completed successfully, otherwise a string error message.
     */
    function run();

    /**
     * Cleanup any resources held by the job.
     *
     * This gives jobs a chance to delete any resources they may be using before the JQManagedJob is removed.
     */
    function cleanup();

    /**
     * Jobs can use this delegate method to report failures, or archive, them, or whatver they want.
     *
     * If some other part of your application needs to track status changes to the JQJob, it can use this callback to do so.
     *
     * @param object JQManagedJob The JQManagedJob for this job.
     * @param string The prior status (see JQManagedJob::STATUS_*).
     * @param string The message accompanying the status change.
     * @see JQStore::statusDidChange()
     */
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message);

    /**
     * A description for the job.
     *
     * This is mostly for reporting/logging purposes.
     *
     * @return string
     */
    function description();

    /**
     * A unique ID for the job.
     *
     * If NULL, the job will not be subject to coalescing.
     *
     * If NOT NULL, the job will not be queued if there already exist a job for the provided coalesceId.
     *
     * The scope for coalesceId is Application. Thus an application needs to take care to prevent collisions of the coalesceId across jobs of different types.
     *
     * The recommended way to do that is to prefix the coalesceId with the class name of the job.
     *
     * @return string
     */
    function coalesceId();
}

/**
 * JQManagedJob is the core unit of work for the Job system.
 *
 * Each job enqueued is wrapped in a JQManagedJob and persisted to a JQStore. The JQManagedJob contains
 * various metadata to track the job through the process of being performed.
 */
final class JQManagedJob implements JQJob
{
    const STATUS_UNQUEUED       = 'unqueued';
    const STATUS_QUEUED         = 'queued';
    const STATUS_RUNNING        = 'running';
    const STATUS_COMPLETED      = 'completed';
    const STATUS_FAILED         = 'failed';

    protected $jobId;
    /**
     * @var object DateTime Creation date of JQManagedJob
     */
    protected $creationDts;
    /**
     * @var object DateTime Scheduled start time, if any, if queued, or if completed/finished, the actual start time.
     */
    protected $startDts;
    /**
     * @var object DateTime Actual end time of job run.
     */
    protected $endDts;
    /**
     * Status state transition diagram:
     * 
     * STATUS_UNQUEUED -+-> STATUS_QUEUED -> STATUS_RUNNING -+-> STATUS_COMPLETED
     *                  ^                                    |
     *                  \--- STATUS_QUEUED (retry) ---------<+
     *                                                       \-> STATUS_FAILED
     *
     *
     * @var string The status of the job, see JQManagedJob::STATUS*.
     */
    protected $status;
    /**
     * @var string A user-assigned string to partition jobs into buckets for the benefit of the application.
     *             Use-cases for queueName include having different "queues" in an app which have workers on different machines with different resources.
     */
    protected $queueName;
    /**
     * @var integer The priority for the job. Default: 0. Higher numbers are higher priority.
     */
    protected $priority;
    /**
     * @var int The maximum number of attempts that a job can have before it should be failed.
     */
    protected $maxAttempts;
    /**
     * @var int The current attempt number; first attempt is 1.
     */
    protected $attemptNumber;
    /**
     * @var string The error message reported from the most recent attempt.
     */
    protected $errorMessage;
    /**
     * @var object JQJob
     */
    protected $job;
    /**
     * @var string coalesceId Duplicate jobs in the same queueName with the same coalesceId are not allowed. enqueue() will succeed, but will return the existing job.
     */
    protected $coalesceId;

    /**
     * @var boolean An internal lock to prevent a job from being run multiple times. Probably overkill?
     */
    private $isRunningLock = false;

    /**
     * Constructor for JQManagedJob.
     *
     * @param object JQStore
     * @param array Options for the job:
     *              startDts:       object DateTime - do not start job before this time, default NULL (asap)
     *              priority:       int             - priority of the job, default 0
     *              maxAttempts:    int             - maximum number of attempts allowed for this job, default 1
     *              queueName:      string          - the queueName to associate this job with
     */
    public function __construct($jqStore, $options = array())
    {
        $this->jqStore = $jqStore;
        $this->creationDts = new DateTime();
        $this->status = JQManagedJob::STATUS_UNQUEUED;
        $this->priority = 0;
        $this->maxAttempts = 1;
        $this->attemptNumber = 0;

        if (isset($options['startDts']))
        {
            $this->startDts = $options['startDts'];
        }
        if (isset($options['priority']))
        {
            $this->priority = $options['priority'];
        }
        if (isset($options['maxAttempts']))
        {
            $this->maxAttempts = $options['maxAttempts'];
        }
        if (isset($options['queueName']))
        {
            $this->queueName = $options['queueName'];
        }
    }

    public function persistableFields()
    {
        return array(
            'jobId',
            'creationDts',
            'startDts',
            'endDts',
            'status',
            'queueName',
            'job',
            'coalesceId',
            'maxAttempts',
            'attemptNumber',
            'priority',
            'errorMessage'
        );
    }

    /**
     * Used by JQStore to get the persistable representation of the JQManagedJob
     *
     * @param array Options:
     *              dtsFormat => default No dts formatting
     * @return array An associative array of the data that needs to be persisted.
     */
    public function toArray($options = array())
    {
        $array = array();
        foreach ($this->persistableFields() as $k) {
            switch ($k) {
                case 'job':
                    $ser = serialize($this->$k);
                    $v = base64_encode($ser);
                    break;
                case 'creationDts':
                case 'startDts':
                case 'endDts':
                    if (isset($options['dtsFormat']))
                    {
                        $v = NULL;
                        if ($this->$k !== NULL)
                        {
                            $v = date_format($this->$k, $options['dtsFormat']);
                        }
                        break;
                    }
                default:
                    $v = $this->$k;
                    break;
            }
            $array[$k] = $v;
        }
        return $array;
    }

    /**
     * Used by JQStore to instantiate a JQManagedJob from the persisted data.
     *
     * @param array An associative array of the data that was persisted.
     * @return ojbect JQManagedJob
     */
    public function fromArray($data)
    {
        foreach ($this->persistableFields() as $k) {
            switch ($k) {
                case 'job':
                    $ser = base64_decode($data[$k]);
                    $v = unserialize($ser);
                    break;
                case 'creationDts':
                case 'startDts':
                case 'endDts':
                    if (is_string($data[$k]))
                    {
                        if (is_numeric($data[$k]))
                        {
                            $v = new DateTime( date('c', $data[$k]) );
                        }
                        else
                        {
                            $v = new DateTime($data[$k]);
                        }
                        break;
                    }
                default:
                    $v = $data[$k];
                    break;
            }
            $this->$k = $v;
        }
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setJob(JQJob $job)
    {
        $this->job = $job;
    }

    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    public function setCoalesceId($coalesceId)
    {
        $this->coalesceId = $coalesceId;
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($newStatus)
    {
        // change status
        $oldStatus = $this->status;
        $this->status = $newStatus;

        // sanity check -- if has all valid state transitions
        if (!(
                 ($oldStatus === self::STATUS_UNQUEUED && $newStatus === self::STATUS_QUEUED)
                 OR ($oldStatus === self::STATUS_QUEUED && $newStatus === self::STATUS_RUNNING)
                 OR ($oldStatus === self::STATUS_RUNNING && in_array($newStatus, array(self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED)))
            ))
        {
            throw new Exception("Invalid state change: {$oldStatus} => {$newStatus}");
        }

        // inform interested parties of status change
        $this->statusDidChange($this, $oldStatus, $this->errorMessage);
    }

    public function getQueueName()
    {
        return $this->queueName;
    }

    public function getAttemptNumber()
    {
        return $this->attemptNumber;
    }

    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }

    public function getStartDts()
    {
        return $this->startDts;
    }

    public function getEndDts()
    {
        return $this->endDts;
    }

    /**
     * Mark the job as started.
     *
     * This function is designed to be called from JQStore in a transaction when selecting the next job to process.
     * Calling this function during the transaction ensures that job "checkout" is atomic.
     *
     * This function tells the JQStore to save the JQManagedJob.
     */
    public function markJobStarted()
    {
        $this->startDts = new DateTime();
        $this->endDts = NULL;
        $this->setStatus(JQManagedJob::STATUS_RUNNING);
        $this->errorMessage = NULL;
        $this->attemptNumber++;
        $this->save();
    }

    /**
     * Mark the job as completed.
     *
     * This function tells the JQStore to delete the JQManagedJob.
     */
    private function markJobComplete()
    {
        $this->endDts = new DateTime();
        $this->setStatus(JQManagedJob::STATUS_COMPLETED);
        $this->delete($this);
    }

    /**
     * Mark the job as failed.
     *
     * This function will either re-queue or fail the job based on maxAttempts vs. attemptNumber.
     *
     * This function tells the JQStore to save the JQManagedJob.
     *
     * @param string The error message generated by the failed job.
     */
    private function markJobFailed($errorMessage = NULL)
    {
        $this->errorMessage = $errorMessage;
        $this->endDts = new DateTime();

        if ($this->getAttemptNumber() < $this->getMaxAttempts()) // retry
        {
            // @todo it's a little lame that this doesn't re-queue at the end of the queue; or maybe jobs should have requeue option; END, after X seconds; right away? longer each time, @ 2x last run time?
            $this->startDts->modify("+10 seconds");
            $this->endDts = NULL;
            $this->setStatus(JQManagedJob::STATUS_QUEUED);
        }
        else                                                    // fail for good
        {
            $this->setStatus(JQManagedJob::STATUS_FAILED);
        }
        $this->save();
    }

    /**
     * Update the job in the JQStore.
     */
    private function save()
    {
        $this->jqStore->save($this);
    }

    /**
     * Delete the job from the JQStore.
     *
     * This function ensures that {@link JQJob::cleanup()} is executed.
     */
    private function delete()
    {
        $this->cleanup();
        $this->jqStore->delete($this);
    }

    /**
     * Error handler callback for PHP catchable errors; allows us to gracefully fail jobs that encounter PHP errors (instead of abandoning a job in the running state)
     * Handles: E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR => 4597
     * It's important to use this set of errors as otherwise you can end up accidentally disabling autoload.
     */
    const HANDLED_ERRORS = 4597;
    function checkShutdownForFatalErrors()
    {
        $last_error = error_get_last();
        if ($last_error['type'] & self::HANDLED_ERRORS)
        {
            $this->markJobFailed("{$last_error['type']}: {$last_error['message']}\n\nAt {$last_error['file']}:{$last_error['line']}");
        }
    }
    function phpErrorToException($errno, $errstr, $errfile, $errline)
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }


    /**
     * Run the job.
     *
     * @return mixed Error from run; NULL if no error, a string message if there was an error.
     * @see JQJob
     */
    public function run()
    {
        if ($this->isRunningLock) throw new Exception("Local run lock already in use... can't run a job twice.");
        $this->isRunningLock = true;

        register_shutdown_function(array($this, 'checkShutdownForFatalErrors'));
        set_error_handler(array($this, 'phpErrorToException'), self::HANDLED_ERRORS);

        // run the job
        $err = NULL;
        try {
            $err = $this->job->run();
        } catch (Exception $e) {
            $err = $e->getMessage();
        }

        restore_error_handler();

        if ($err)
        {
            $this->markJobFailed($err);
        }
        else
        {
            $this->markJobComplete();
        }

        $this->isRunningLock = false;

        return $err;
    }

    /**
     * @see JQJob
     */
    public function coalesceId()
    {
        return $this->job->coalesceId();
    }

    /**
     * @see JQJob
     */
    public function cleanup()
    {
        $this->job->cleanup();
    }

    /**
     * @see JQJob
     */
    public function statusDidChange(JQManagedJob $mJob, $oldStatus, $message)
    {
        $this->job->statusDidChange($mJob, $oldStatus, $message);
        $this->jqStore->statusDidChange($this, $oldStatus, $message);
    }

    /**
     * @see JQJob
     */
    public function description()
    {
        return $this->job->description();
    }
}

/**
 * The JQStore interface.
 *
 * The JQJob system uses an interface for services that can implement the backing store for the queued jobs.
 *
 * This allows queue clients to easily switch between different backing stores without having to re-do any work.
 * Depending on the needs of your system you can use a simple queuestore (easy to set up but slower)
 * or a more complex one (harder to set up but supports higher throughput, concurrent access, etc).
 */
interface JQStore
{
    /**
     * Add a JQJob to the queue.
     *
     * Will create a new JQManagedJob to manage the job and add it to the queue.
     *
     * @param object JQJob
     * @param array Options: priority, maxAttempts
     * @return object JQManagedJob
     * @throws object Exception
     */
    function enqueue(JQJob $job, $options = array());

    /**
     * Get the next job to runin the queue.
     *
     * NOTE: Implementers should make sure that next() has a mutex to be sure that no two workers end up running the same job twice.
     *
     * @param string Queue name (NULL = default queue)
     * @return object JQManagedJob
     */
    function next($queueName = null);

    /**
     * See if there is already a job for the given coalesceId in the queue.
     *
     * Don't forget that in the present implementation, successful jobs are deleted (and thus the same job can run again once successfully completed)
     * but that failed jobs are kept, meaning that you cannot re-run the job until the failed job is deleted manually.
     *
     * Notes for implementer: A NULL coalesceId should always return NULL.
     * Notes for implementer: Your enqueue() function should call this to find existing jobs. Think about the concurrency consequences of this.
     *
     * @param string The coalesceId to check for.
     * @return object JQManagedJob
     */
    function existsJobForCoalesceId($coalesceId);

    /**
     * Count the jobs in the given queue with the given status (any).
     *
     * @param string Queue name (NULL = default queue)
     * @param string Status (NULL = any status)
     */
    function count($queueName = null, $status = NULL);

    /**
     * Get a JQManagedJob from the JQStore by ID.
     *
     * @param string JobId.
     * @return object JQStore, or NULL if not found.
     */
    function get($jobId);

    /**
     * Save the job (which presumably has been updated) to the backing store.
     *
     * @param object JQManagedJob
     */
    function save(JQManagedJob $job);

    /**
     * Delete the job from the backing store.
     *
     * @param object JQManagedJob
     */
    function delete(JQManagedJob $job);

    /**
     * JQStore's can use this delegate method to report failures, or archive, them, or whatver they want.
     *
     * If your application needs an audit log, you can subclass your JQStore and implement this method to easily add JQJobs-wide audit logging.
     *
     * @param object JQManagedJob The JQManagedJob for this job.
     * @param string The prior status (see JQManagedJob::STATUS_*).
     * @param string The message accompanying the status change.
     * @see JQJob::statusDidChange()
     */
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message);
}

/**
 * A simple in-memory array-based queue store.
 *
 * Useful for testing/developing queues and also extremely simple queueing situations that run the lifetime of a single PHP process.
 */
class JQStore_Array implements JQStore
{
    protected $queue = array();
    protected $jobId = 1;

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

    public function next($queueName = NULL)
    {
        foreach ($this->queue as $dbJob) {
            if ($dbJob->getStatus() === JQManagedJob::STATUS_QUEUED && $dbJob->getQueueName() === $queueName)
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

/**
 * A persistent queue store, stored in a DB via a Propel class.
 *
 * The propel class should have all fields coded in JQManagedJob::persistableFields().
 *
 * This queue is suitable for moderate workloads (dozens of jobs per second) and supports concurrent access from multiple workers.
 */
class JQStore_Propel implements JQStore
{
    protected $con = NULL;
    protected $propelClassName;
    protected $options;

    public function __construct($propelClassName, $con, $options = array())
    {
        $this->propelClassName = $propelClassName;
        $this->con = $con;

        $this->options = array_merge(array(
                                            'tableName'                 => 'JQStoreManagedJob',
                                            'jobIdColName'              => 'JOB_ID',
                                            'jobCoalesceIdColName'      => 'COALESCE_ID',
                                            'jobQueueNameColName'       => 'QUEUE_NAME',
                                            'jobStatusColName'          => 'STATUS',
                                            'jobPriorityColName'        => 'PRIORITY',
                                            'jobStartDtsColName'        => 'START_DTS',
                                            'jobEndDtsColName'          => 'END_DTS',
                                            'toArrayOptions'            => array('dtsFormat' => 'r')
                                          ),
                                     $options
                                    );
        // eval propel constants, thanks php 5.2 :( On 5.3 I think we can do $this->propelClassName::TABLE_NAME etc...
        $this->options['tableName'] = eval("return {$this->propelClassName}Peer::TABLE_NAME;");
        foreach (array('jobIdColName', 'jobQueueNameColName', 'jobStatusColName', 'jobPriorityColName', 'jobStartDtsColName', 'jobEndDtsColName') as $colName) {
            $this->options[$colName] = eval("return {$this->propelClassName}Peer::{$this->options[$colName]};");
        }
    }

    public function enqueue(JQJob $job, $options = array())
    {
        $mJob = NULL;
 
        $this->con->beginTransaction();
        try {
            // lock the table so we can be sure to get mutex to safely enqueue job without risk of having a colliding coalesceId.
            // EXCLUSIVE mode is used b/c it's the most exclusive mode that doesn't conflict with pg_dump (which uses ACCESS SHARE)
            // see http://stackoverflow.com/questions/6507475/job-queue-as-sql-table-with-multiple-consumers-postgresql/6702355#6702355
            // theoretically this lock should prevent the unique index from ever tripping.
            $lockSql = "lock table {$this->options['tableName']} in EXCLUSIVE mode;";
            $this->con->query($lockSql);
 
            // look for coalesceId collision
            $mJob = $this->existsJobForCoalesceId($job->coalesceId());
            if (!$mJob)
            {
                // create a new job
                $mJob = new JQManagedJob($this, $options);
                $mJob->setJob($job);
                $mJob->setStatus(JQManagedJob::STATUS_QUEUED);
                
                $dbJob = new $this->propelClassName;
                $dbJob->fromArray($mJob->toArray($this->options['toArrayOptions']), BasePeer::TYPE_STUDLYPHPNAME);
                $dbJob->save($this->con);
 
                $mJob->setJobId($dbJob->getJobId());
            }
 
            $this->con->commit();
        } catch (PropelException $e) {
            $this->con->rollback();
            throw $e;
        }

        return $mJob;
    }
    
    public function existsJobForCoalesceId($coalesceId)
    {
        if ($coalesceId === NULL)
        {
            return NULL;
        }

        $c = new Criteria;
        $c->add($this->options['jobCoalesceIdColName'], $coalesceId);
        $existingJob = call_user_func_array(array("{$this->propelClassName}Peer", 'doSelectOne'), $c, $this->con);

        return $existingJob;
    }

    public function next($queueName = NULL)
    {
        $nextMJob = NULL;

        $this->con->beginTransaction();
        try {
            // lock the table so we can be sure to get mutex access to "next" job
            // EXCLUSIVE mode is used b/c it's the most exclusive mode that doesn't conflict with pg_dump (which uses ACCESS SHARE)
            // see http://stackoverflow.com/questions/6507475/job-queue-as-sql-table-with-multiple-consumers-postgresql/6702355#6702355
            $lockSql = "lock table {$this->options['tableName']} in EXCLUSIVE mode;";
            $this->con->query($lockSql);

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
    public function get($jobId)
    {
        $dbJob = call_user_func(array("{$this->propelClassName}Peer", 'retrieveByPK'), $jobId, $this->con);
        if (!$dbJob) throw new Exception("Couldn't find jobId {$jobId} in database.");
        $mJob = new JQManagedJob($this);
        $mJob->fromArray($dbJob->toArray(BasePeer::TYPE_STUDLYPHPNAME));
        return $dbJob;
    }

    public function save(JQManagedJob $mJob)
    {
        $dbJob = $this->get($mJob->getJobId());
        $dbJob->fromArray($mJob->toArray($this->options['toArrayOptions']), BasePeer::TYPE_STUDLYPHPNAME);
        $dbJob->save($this->con);
    }

    public function delete(JQManagedJob $mJob)
    {
        $dbJob = $this->get($mJob->getJobId());
        $dbJob->delete($this->con);
    }

    /**
     * Status changed hook.
     *
     * No action by default; subclass and override if you want JQStore-wide logging, etc.
     */
    public function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
}

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
                $this->log("[Job: {$nextJob->getJobId()} RUNNING] {$nextJob->getJob()->description()}", true);
                $result = $nextJob->run();
                if ($result === NULL)
                {
                    $this->log("[Job: {$nextJob->getJobId()} COMPLETED]", true);
                }
                else
                {
                    $this->log("[Job: {$nextJob->getJobId()} FAILED] {$result}");
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
