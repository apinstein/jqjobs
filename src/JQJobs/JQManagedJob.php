<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

class JQManagedJob_AlreadyHasAJobException extends Exception { }

/**
 * JQManagedJob is the core unit of work for the Job system.
 *
 * Each job enqueued is wrapped in a JQManagedJob and persisted to a JQStore. The JQManagedJob contains
 * various metadata to track the job through the process of being performed.
 */
final class JQManagedJob extends JQJob
{
    const STATUS_UNQUEUED       = 'unqueued';
    const STATUS_QUEUED         = 'queued';
    const STATUS_RUNNING        = 'running';
    const STATUS_WAIT_ASYNC     = 'wait_async';
    const STATUS_COMPLETED      = 'completed';
    const STATUS_FAILED         = 'failed';

    // don't bump maxAttempts during mulligans if maxAttempts >= MULLIGAN_MAX_ATTEMPTS
    const MULLIGAN_MAX_ATTEMPTS = 20;

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
     * STATUS_UNQUEUED -+-> STATUS_QUEUED -> STATUS_RUNNING -> [optional] STATUS_WAIT_ASYNC --+-> STATUS_COMPLETED
     *                  ^                                                                     |
     *                  \--- STATUS_QUEUED (retry) ------------------------------------------<+
     *                                                                                         \-> STATUS_FAILED
     *
     * Note that if a job is in STATUS_WAIT_ASYNC other jobs can continue running.
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
     * @var int maxRuntimeSeconds The maximum number of seconds before a STATUS_RUNNING job is considered "HUNG" and is eligible for a mulligan retry.
     *                            This feature essentially auto-detects aborted states (due to workers that didn't clean up properly) and allows retry.
     */
    protected $maxRuntimeSeconds;

    /**
     * @var boolean An internal lock to prevent a job from being run multiple times. Probably overkill?
     */
    private $isRunningLock = false;

    /**
     * @var boolean Delete the job once completed?
     */
    private $deleteOnComplete = true;

    /**
     * Constructor for JQManagedJob.
     *
     * @param object JQStore
     * @param array Options for the job:
     *           startDts: DateTime - do not start job before this time, default NULL (asap)
     *           priority: int      - priority of the job, default 0
     *        maxAttempts: int      - maximum number of attempts allowed for this job, default 1
     *          queueName: string   - the queueName to associate this job with
     *   deleteOnComplete: boolean  - delete the job when done? default false
     *  maxRuntimeSeconds: int      - seconds that the job is allowed to be in STATUS_RUNNING before it's determined to be aborted. default null (no auto-cleanup)
     */
    public function __construct($jqStore, $jqJob = NULL)
    {
        $this->jqStore = $jqStore;
        $this->creationDts = new DateTime();
        $this->status = JQManagedJob::STATUS_UNQUEUED;
        $this->priority = 0;
        $this->maxAttempts = 1;
        $this->attemptNumber = 0;
        $this->maxRuntimeSeconds = NULL;
        if(isset($jqJob))
        {
            $this->setJob($jqJob);
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
            'errorMessage',
            'maxRuntimeSeconds'
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
                    $job = $this->$k;
                    if ($job instanceof JQJob)
                    {
                        $ser = serialize($this->$k);
                        // @todo -- should there be some error handling here?
                        $v = base64_encode($ser);
                    }
                    else
                    {
                        // already serialized (or null)
                        $v = $this->$k;
                    }
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
            if (!isset($data[$k])) continue;

            switch ($k) {
                case 'job':
                    $unserializedJobBase64 = $data[$k]; // data is base64+serialized, or NULL
                    try {
                        $ser = base64_decode($unserializedJobBase64);
                        if ($ser === false) throw new Exception("base64_decode failed");
                        $v = unserialize($ser); // will result in object JQJob, but this can fail for many reasons
                    } catch (Exception $e) {
                        $v = $unserializedJobBase64;
                    }
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
        if ( $this->job !== NULL)
        {
            throw new JQManagedJob_AlreadyHasAJobException();
        }
        $this->job = $job;
        $this->setCoalesceId($job->coalesceId());

        $options = $job->getEnqueueOptions();

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
        if (isset($options['deleteOnComplete']))
        {
            $this->deleteOnComplete = $options['deleteOnComplete'];
        }
        if (isset($options['maxRuntimeSeconds']))
        {
            $this->maxRuntimeSeconds = $options['maxRuntimeSeconds'];
        }
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

    public function getPriority()
    {
        return $this->priority;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($newStatus)
    {
        // change status
        $oldStatus = $this->status;

        // no-op check
        if ($oldStatus === $newStatus) return;

        // sanity check -- "if" statement below has all valid state transitions
        if (!(
                 ($oldStatus === self::STATUS_UNQUEUED && $newStatus === self::STATUS_QUEUED)
                 OR ($oldStatus === self::STATUS_QUEUED && $newStatus === self::STATUS_RUNNING)
                 OR ($oldStatus === self::STATUS_RUNNING && in_array($newStatus, array(self::STATUS_WAIT_ASYNC, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_QUEUED)))
                 OR ($oldStatus === self::STATUS_WAIT_ASYNC && in_array($newStatus, array(self::STATUS_RUNNING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_QUEUED)))
                 OR ($oldStatus === self::STATUS_FAILED && $newStatus === self::STATUS_QUEUED)
            ))
        {
            throw new Exception("Invalid state change: {$oldStatus} => {$newStatus}");
        }

        $this->status = $newStatus;

        // inform interested parties of status change
        try {
            $this->statusDidChange($this, $oldStatus, $this->errorMessage);
        } catch (JQWorker_SignalException $e) {
            throw $e;
        } catch (Exception $e) {
            // @todo we need a logger here?
            print "Userland statusDidChange() reported an error: {$e->getMessage()}";
        }
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function getQueueName()
    {
        return $this->queueName;
    }

    public function getAttemptNumber()
    {
        return $this->attemptNumber;
    }

    public function setAttemptNumber($n)
    {
        $this->attemptNumber = $n;
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

    public function getMaxRuntimeSeconds()
    {
        return $this->maxRuntimeSeconds;
    }

    public function isPastMaxRuntimeSeconds()
    {
        if ($this->getStatus() !== JQManagedJob::STATUS_RUNNING) return false;
        if ($this->maxRuntimeSeconds === NULL) return false;

        $startDtsEpoch = $this->getStartDts()->format('U');
        $expirationDtsEpoch = $startDtsEpoch + $this->maxRuntimeSeconds;
        $now = time();
        return $now > $expirationDtsEpoch;
    }

    /**
     * Mark the job as started.
     *
     * This function is designed to be called from JQStore in a transaction when selecting the next job to process.
     * Calling this function during the transaction ensures that job "checkout" is atomic.
     *
     * This function tells the JQStore to save the JQManagedJob.
     */
    public function markJobStarted(DateTime $asOf = NULL)
    {
        if ($asOf === NULL)
        {
            $asOf = new DateTime();
        }
        $this->startDts = $asOf;
        $this->endDts = NULL;
        $this->setStatus(JQManagedJob::STATUS_RUNNING);
        $this->errorMessage = NULL;
        $this->attemptNumber++;
        $this->save();
    }

    /**
     * Mark the job as waiting -- used for jobs that call out to asynchronous collaborators.
     * 
     * When the job finishes, it should call markJobComplete() or markJobFailed().
     */
    private function markJobWaitAsync()
    {
        $this->setStatus(JQManagedJob::STATUS_WAIT_ASYNC);
        $this->save();
    }

    /**
     * Mark the job as completed.
     *
     * This function tells the JQStore to delete the JQManagedJob.
     */
    public function markJobComplete()
    {
        $this->isRunningLock = false;

        $this->endDts = new DateTime();
        $this->setStatus(JQManagedJob::STATUS_COMPLETED);

        if ($this->deleteOnComplete)
        {
            $this->delete($this);
        }
        else
        {
            $this->save();
        }
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
    public function markJobFailed($errorMessage = NULL, $mulligan = false)
    {
        $this->isRunningLock = false;

        $didQueueForRetry = $this->retry($mulligan);
        if (!$didQueueForRetry)
        {
            $this->errorMessage = $errorMessage;
            $this->endDts = new DateTime();
            $this->setStatus(JQManagedJob::STATUS_FAILED);
            $this->save();
        }
    }

    /**
     * Re-queue the job so that it will be attempted again.
     *
     * @param boolean Mulligan mode? If TRUE will bump maxAttempts as well
     * @return boolean TRUE if the job was able to be queued for retry
     */
    public function retry($mulligan = false)
    {
        // implement mulligan feature
        if ($mulligan && $this->maxAttempts < self::MULLIGAN_MAX_ATTEMPTS)
        {
            $this->maxAttempts++;
        }

        if ($this->attemptNumber >= $this->maxAttempts)
        {
            // no retries left
            return false;
        }
        else
        {
            // OK to retry
            // @todo it's a little lame that this doesn't re-queue at the end of the queue; or maybe jobs should have requeue option; END, after X seconds; right away? longer each time, @ 2x last run time?
            $this->startDts->modify("+10 seconds");
            $this->endDts = NULL;
            $this->setStatus(JQManagedJob::STATUS_QUEUED);
            $this->save();
            return true;
        }
    }

    /**
     * Restart the current job as if it had never been run before.
     *
     * Unlike retry, this *always* works. This is most useful as a manual override to all built-in retry semantics.
     *
     * @see retry
     */
    public function restart()
    {
        $this->attemptNumber = 0;
        $this->startDts = NULL;
        $this->endDts = NULL;
        $this->setStatus(JQManagedJob::STATUS_QUEUED);
        $this->save();
    }

    /**
     * Update the job in the JQStore.
     */
    public function save()
    {
        $this->jqStore->save($this);
    }

    /**
     * Delete the job from the JQStore.
     *
     * This function ensures that {@link JQJob::cleanup()} is executed.
     */
    public function delete()
    {
        $this->cleanup();
        $this->jqStore->delete($this);
    }

    function errorHandlerFailJob($errorException)
    {
        $this->markJobFailed($errorException->getMessage());
    }

    /**
     * Run the job.
     *
     * @return mixed Error from run; NULL if no error, a string message if there was an error.
     * @see JQJob
     */
    public function run(JQManagedJob $job)
    {
        if ($this !== $job) throw new Exception("Must pass in the JQManagedJob, and yes, I know it's the same as the object used to call the run() method on. This is just a test to be sure you're paying attention.");
        if (!($this->job instanceof JQJob)) throw new Exception("JQManagedJob.job is not a JQJob instance. Nothing to run.");

        if ($this->isRunningLock) throw new Exception("Local run lock already in use... can't run a job twice.");
        $this->isRunningLock = true;

        // run the job
        $err = NULL;
        try {
            $disposition = ErrorManager::wrap(array($this->job, 'run'), array($this), array($this, 'errorHandlerFailJob'));
        } catch (JQWorker_SignalException $e) {
            // NOTE: a signal interrupts any line of code; from the try/catch above thru the cleanup code below
            // we throw here so that the same workflow can be used for errors in the try/catch and outsie of it
            // that way all signal handling during job execution is handled in one path, in the JQWorker.
            throw $e;
        } catch (Exception $e) {
            $err = $e->getMessage();
            $disposition = self::STATUS_FAILED;
        }

        switch ($disposition) {
            case self::STATUS_COMPLETED:
                $this->markJobComplete();
                break;
            case self::STATUS_WAIT_ASYNC:
                $this->markJobWaitAsync();
                break;
            case self::STATUS_FAILED:
                $this->markJobFailed($err);
                break;
            default:
                throw new Exception("Invalid return value " . var_export($disposition, true) . " from job->run(). Return one of JQManagedJob::STATUS_COMPLETED, JQManagedJob::STATUS_WAIT_ASYNC, or JQManagedJob::STATUS_FAILED.");
        }

        return $err;
    }

    /**
     * @see JQJob
     */
    public function coalesceId()
    {
        return $this->coalesceId;
    }

    /**
     * @see JQJob
     */
    public function cleanup()
    {
        if (!($this->job instanceof JQJob)) return;

        $this->job->cleanup();
    }

    /**
     * @see JQJob
     */
    public function statusDidChange(JQManagedJob $mJob, $oldStatus, $message)
    {
        if ($this->job instanceof JQJob)
        {
            $this->job->statusDidChange($mJob, $oldStatus, $message);
        }
        $this->jqStore->statusDidChange($this, $oldStatus, $message);
    }

    /**
     * @see JQJob
     */
    public function description()
    {
        if (!($this->job instanceof JQJob)) return "Job Id {$this->jobId}: JQManagedJob.job is empty.";
        return $this->job->description();
    }
}

/**
 * @todo Factor this out into its own package -- many things could benefit from it.
 */
class ErrorManager
{
    /**
     * Error handler callback for PHP catchable errors; allows us to gracefully fail jobs that encounter PHP errors (instead of abandoning a job in the running state)
     * Handles: E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR => 4597
     * It's important to use this set of errors as otherwise you can end up accidentally disabling autoload.
     */
    const HANDLED_ERRORS = 4597;
    const IGNORED_ERROR  = 'ErrorManager::NO_ERROR_PLACEHOLDER';

    protected static $shutdownErrorDetectorInstalled = false;
    protected static $shutdownErrorDetectorEnabled   = false;
    protected static $currentHandlerStack            = array();

    /**
     * Run some PHP function, making sure that any errors are routed to the error handler.
     *
     * This function will convert any trigger_error style errors and throw an ErrorException, and also detect catchable fatal errors
     * and pass them to the shutdownErrorHandler. Unfortunately shutdown errors cannot be used to throw Exceptions in your call stack.
     *
     * @param callable The code you want to run.
     * @param array An array of arguments you need passed to your function, or NULL if no args.
     * @param callable The shutdown error handler, default NULL.
     * @return mixed The return value from your callback function.
     */
    public static function wrap($f, $fArgs = NULL, $shutdownErrorHandler = NULL)
    {
        if ($fArgs === NULL)
        {
            $fArgs = array();
        }
        if ($shutdownErrorHandler === NULL)
        {
            $shutdownErrorHandler = array('ErrorManager', 'errorHandlerNoop');
        }

        self::install($shutdownErrorHandler);
        $ret = call_user_func_array($f, $fArgs ? $fArgs : array());
        self::remove();

        return $ret;
    }

    private static function install($errorHandler)
    {
        array_push(self::$currentHandlerStack, $errorHandler);

        // Install error handlers
        set_error_handler(array('ErrorManager', 'errorHandlerRaiseErrorExceptionHandler'), self::HANDLED_ERRORS);
        if (!self::$shutdownErrorDetectorInstalled) // only do this once every; unfortunately there's no un-install
        {
            register_shutdown_function(array('ErrorManager', 'shutdownErrorDetector'));
            self::$shutdownErrorDetectorInstalled = true;
        }
        self::$shutdownErrorDetectorEnabled = true;

        // fire our "ignore" error, make sure it doesn't show
        // @ supresses OUTPUT but error will still call error handlers and appear in error_get_last
        @trigger_error(self::IGNORED_ERROR, E_USER_NOTICE);
    }

    private static function remove()
    {
        array_pop(self::$currentHandlerStack);

        // uninstall error handlers
        restore_error_handler();
        if (empty(self::$currentHandlerStack))
        {
            self::$shutdownErrorDetectorEnabled = false;
        }
    }

    /**
     * A handler for set_error_handler() which raises an ErrorException for "desired" errors.
     *
     * NOTE: this function will eat our magic "ignored" error
     */
    public static function errorHandlerRaiseErrorExceptionHandler($errno, $errstr, $errfile, $errline)
    {
        if ($errstr === self::IGNORED_ERROR) return;

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public static function errorHandlerNoop($errno, $errstr, $errfile, $errline)
    {
    }

    /**
     * Calls error_get_last() and returns NULL or an object ErrorException
     * @return object ErrorException ErrorException object, or NULL if there's no current error.
     */
    public static function error_get_last_ToErrorException()
    {
        $e = error_get_last();
        if (!$e) return NULL;
        if ($e['message'] === self::IGNORED_ERROR) return NULL;

        return new ErrorException($e['message'], 0, $e['type'], $e['file'], $e['line']);
    }

    public static function shutdownErrorDetector()
    {
        if (!self::$shutdownErrorDetectorEnabled) return;

        // give ourselves a little more memory so we can process the exception in the case of an OOM
        ini_set('memory_limit', memory_get_usage() + 25000000 /* 25MB */);

        // check for error
        $lastError = self::error_get_last_ToErrorException();
        if ($lastError)
        {
            $handler = array_pop(self::$currentHandlerStack);
            call_user_func($handler, $lastError);
        }
    }
}
