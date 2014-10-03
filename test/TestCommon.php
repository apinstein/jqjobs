<?php

require_once dirname(__FILE__) . '/../src/JQJobs/JQJobs.php';

$composerAutoloader = dirname(__FILE__) . '/../vendor/autoload.php';
if (file_exists($composerAutoloader))
{
    ini_set('include_path', dirname(__FILE__) . "/../lib/propel");
    require_once $composerAutoloader;
}
else
{
    ini_set('include_path', 
        dirname(__FILE__) . "/../externals/pear/php"
        . ":" . dirname(__FILE__) . "/../lib/propel"
    );
    require_once 'propel/Propel.php';
}

class JQJobs_TestHelper
{
    /**
     * Utility function to help bootstrap states for testing.
     */
    public static function moveJobToStatus($mJob, $targetStatus)
    {
        // map on how to bootstrap a job to the "FROM" state
        $pathForSetup = array(
            JQManagedJob::STATUS_UNQUEUED       => array(),
            JQManagedJob::STATUS_QUEUED         => array(JQManagedJob::STATUS_QUEUED),
            JQManagedJob::STATUS_RUNNING        => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING),
            JQManagedJob::STATUS_WAIT_ASYNC     => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING, JQManagedJob::STATUS_WAIT_ASYNC),
            JQManagedJob::STATUS_COMPLETED      => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING, JQManagedJob::STATUS_COMPLETED),
            JQManagedJob::STATUS_FAILED         => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING, JQManagedJob::STATUS_FAILED),
        );

        foreach ($pathForSetup[$targetStatus] as $s) {
            $mJob->setStatus($s);
        }
    }
}

Propel::init(dirname(__FILE__) . "/../lib/propel/jqjobs-conf.php");

/************** JQStore_Propel Genterator (TEST DB) ********************/

function getTestJQStore()
{
    return new JQStore_Propel('JQStoreManagedJob', Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME));
}

/************** TEST JOBS ****************/

// A simple no-op job that uses a test queue for other test jobs to override
class JQTestJob implements JQJob
{
    private $enqueueOptions = array(
        'priority'    => 0,
        'maxAttempts' => 1,
        'queueName'   => 'test',
    );

    function __construct($enqueueOptions = array())
    {
        $this->setEnqueueOptions( $enqueueOptions );
    }

    function getEnqueueOptions() { return $this->enqueueOptions; }

    function setEnqueueOption($key, $value)
    {
        $this->setEnqueueOptions( array($key => $value) );
    }

    function setEnqueueOptions($newOptions)
    {
        $this->enqueueOptions = array_merge( $this->enqueueOptions, $newOptions );
    }

    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_COMPLETED; }
    function cleanup() { }
    function coalesceId() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) { }
    function description() { }
}

class ConcurrencyTestJob extends JQTestJob
{
    protected $jobId;
    function __construct($jobid)
    {
        $this->jobId = $jobid;
        parent::__construct( array('queueName' => 'concurrency-test') );
    }
    function run(JQManagedJob $mJob)
    {
        print "running job {$this->jobId}";
        return JQManagedJob::STATUS_COMPLETED;
    }
    function description() { return "job {$this->jobId}"; }
}

class QuietSimpleJob extends JQTestJob
{
    protected $job;
    function __construct($jobid, $enqueueOptions=array())
    {
        parent::__construct($enqueueOptions);
        $this->job=$jobid;
    }
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_COMPLETED; }
    function description() { return "job {$this->job}"; }
}

class SampleJobCounter
{
    private static $counter = 0;
    public static function reset() { self::$counter = 0; }
    public static function count() { return self::$counter; }
    public static function increment() { self::$counter++; }
}
class SampleJob extends JQTestJob
{
    function run(JQManagedJob $mJob) // no-op
    {
        SampleJobCounter::increment();
        return JQManagedJob::STATUS_COMPLETED;
    }
    function description() { return "Sample job"; }
}

class SampleCoalescingJob extends SampleJob
{
    function __construct($id)
    {
        $this->id = $id;
        parent::__construct();
    }
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_COMPLETED; }
    function coalesceId() { return $this->id; }
    function description() { return "Coalescing job {$this->id}"; }
}

class SampleFailJob extends JQTestJob
{
    function __construct($enqueueOptions = array())
    {
        parent::__construct($enqueueOptions);
    }
    function run(JQManagedJob $mJob)
    {
        trigger_error("something went boom", E_USER_ERROR);
        return JQManagedJob::STATUS_FAILED;
    }
    function description() { return "Sample FAIL job"; }
}

class SampleLoggingJob extends JQTestJob
{
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_COMPLETED; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message)
    {
        print "Status change: {$oldStatus} => {$mJob->getStatus()}\n";
    }
    function description() { return "Sample logging job"; }
}

class SampleCallbackJob extends JQTestJob
{
    function __construct($callback) { $this->callback = $callback; }
    function run(JQManagedJob $mJob) { return call_user_func($this->callback); }
    function description() { return "Sample callback job"; }
}

class SampleAsyncJob extends JQTestJob
{
    function __construct($info, $enqueueOptions = array())
    {
        $this->info = $info;
        parent::__construct($enqueueOptions);
    }
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_WAIT_ASYNC; }
    function resolveWaitAsyncJob($goToState)
    {
        if ($goToState === 'SampleAsyncJob_ResolveException') throw new SampleAsyncJob_ResolveException();
        return $goToState;
    }
    function description() { return "Sample async job"; }
}
class SampleAsyncJob_ResolveException extends Exception {}

class SampleExceptionalUnserializerJob extends JQTestJob
{
    public $data = NULL;

    function __construct($someData) {
        $this->data = $someData;
        parent::__construct();
    }
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_WAIT_ASYNC; }
    function description() { return "I throw an exception when unserialized."; }
    function __wakeup() { throw new Exception("__wakeup failed"); }
}
