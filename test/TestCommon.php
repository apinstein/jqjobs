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

Propel::init(dirname(__FILE__) . "/../lib/propel/jqjobs-conf.php");

/************** JQStore_Propel Genterator (TEST DB) ********************/

function getTestJQStore()
{
    return new JQStore_Propel('JQStoreManagedJob', Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME));
}

/************** TEST JOBS ****************/

// A simple no-op job that uses a test queue for other test jobs to override
class JQTestJob extends JQJob
{
    function __construct($enqueueOptions = array())
    {
        $this->setEnqueueOption( 'queueName', 'test' );
        $this->setEnqueueOptions( $enqueueOptions );
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
    function cleanup() {}
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "job {$this->job}"; }
    function coalesceId() { return NULL; }
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
    function coalesceId() { return NULL; }
    function cleanup() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
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
    function cleanup() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
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
    function cleanup() { }
    function coalesceId() { return NULL; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Sample FAIL job"; }
}

class SampleLoggingJob extends JQTestJob
{
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_COMPLETED; }
    function cleanup() { }
    function coalesceId() { return NULL; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message)
    {
        print "Status change: {$oldStatus} => {$mJob->getStatus()}\n";
    }
    function description() { return "Sample logging job"; }
}

class SampleCallbackJob extends JQJob
{
    function __construct($callback) { $this->callback = $callback; }
    function run(JQManagedJob $mJob) { return call_user_func($this->callback); }
    function cleanup() { }
    function coalesceId() { return NULL; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
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
    function cleanup() { }
    function coalesceId() { return NULL; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Sample async job"; }
}

class SampleExceptionalUnserializerJob extends JQTestJob
{
    public $data = NULL;

    function __construct($someData) {
        $this->data = $someData;
        parent::__construct();
    }
    function run(JQManagedJob $mJob) { return JQManagedJob::STATUS_WAIT_ASYNC; }
    function cleanup() { }
    function coalesceId() { return NULL; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "I throw an exception when unserialized."; }
    function __wakeup() { throw new Exception("__wakeup failed"); }
}
