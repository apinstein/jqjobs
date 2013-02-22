<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * USAGE:
 *
 * 1. Include JQJobs and optional JQDelayedJob.
 *
 * require_once 'JQJobs.php';
 * require_once 'JQDelayedJob.php';
 * 
 * 2. Define your callback function that you want to run after the script exits.
 *
 *    function testcallback($a)
 *    {
 *        print "{$a}\n";
 *    }
 *
 * 3. Enqueue the job.
 *
 *    JQDelayedJob::doLater('testcallback', "Hello, World. I am running from a delayed job after the script exits!");
 *
 * 3a. You can also call doLater on a JQJob...
 * 
 *    JQDelayedJob::doLater(new MyJob('data'));
 * 
 * @package JQJobs
 */
class JQDelayedJob implements JQJob
{
    protected $callbackF;
    protected $callbackArgs;
    protected static $jqStore = NULL;

    public function __construct($callbackF, $args = array())
    {
        if (!is_callable($callbackF)) throw new Exception("Valid callback required.");

        $this->callbackF = $callbackF;
        $this->callbackArgs = $args;
    }

    /**
     * Register a function or a JQJob to be executed after the script exits.
     *
     * @param mixed
     *           callback       A valid PHP callback. You can curry arguments to the callback by adding them as additional arguments to JQDelayedJob::doLater().
     *           object JQJob   A JQJob.
     * @throws Exception If no valid callback or job is provided.
     */
    public static function doLater($callbackF)
    {
        // bootstrap...
        if (!self::$jqStore)
        {
            // bootstrap JQStore_Array
            self::$jqStore = new JQStore_Array();

            // register shutdown function
            register_shutdown_function(array('JQDelayedJob', 'handleShutdown'));
        }

        $job = NULL;
        if ($callbackF instanceof JQJob)
        {
            $job = $callbackF;
        }
        else if (is_callable($callbackF))
        {
            // get args to callback
            $args = func_get_args();
            array_shift($args);

            $job = new JQDelayedJob($callbackF, $args);
        }

        if (!$job) throw new Exception("No JQJob or callbackF specified.");

        self::$jqStore->enqueue($job);
    }

    public static function handleShutdown()
    {
        $w = new JQWorker(self::$jqStore, array('exitIfNoJobs' => true, 'silent' => true));
        $w->start();
    }

    // JQJob interface...
    function run(JQManagedJob $mJob)
    {
        call_user_func_array($this->callbackF, $this->callbackArgs);
        return JQManagedJob::STATUS_COMPLETED;
    }
    function cleanup() {}
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Delayed job"; }
    function coalesceId() { return NULL; }
}
