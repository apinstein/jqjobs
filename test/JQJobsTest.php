<?php

require_once dirname(__FILE__) . '/../JQJobs.php';

class JQJobsTest extends PHPUnit_Framework_TestCase
{
    public $counter = 0;

    /**
     * @testdox Test Basic JQJobs Processing
     */
    function testJQJobs()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $this->assertEquals(0, $q->count('test'));

        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob($this), array('queueName' => 'test'));
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $this->counter);
        $this->assertEquals(0, $q->count('test'));
    }

    /**
     * @testdox Test JQJobs catches fatal PHP errors during job execution and marks job as failed.
     */
    function testJqJobsCatchesPHPErrorDuringJob()
    {
        // create a queuestore
        $q = new JQStore_Array();
        $q->enqueue(new SampleFailJob($this), array('queueName' => 'test'));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(0, $q->count('test', 'queued'));
        $this->assertEquals(1, $q->count('test', 'failed'));
    }

    /**
     * @testdox Worker option exitAfterNJobs: NULL means never exit, positive integer will exit after N jobs
     */
    function testWorkerOptionExitAfterNJobs()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $this->assertEquals(0, $q->count('test'));

        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob($this), array('queueName' => 'test'));
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // Start a worker to run 1 job
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 1, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(1, $this->counter);
        $this->assertEquals(9, $q->count('test'));

        // Start a worker to run 2 jobs
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 2, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(3, $this->counter);
        $this->assertEquals(7, $q->count('test'));

        // Start a worker to run remaining
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => NULL, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $this->counter);
        $this->assertEquals(0, $q->count('test'));
    }
}

class SampleJob implements JQJob
{
    function __construct($info) { $this->info = $info; }
    function run() { $this->info->counter++; } // no-op
    function cleanup() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Sample job"; }
}

class SampleFailJob implements JQJob
{
    function __construct($info) { $this->info = $info; }
    function run() { trigger_error("something went boom", E_USER_ERROR); }
    function cleanup() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Sample FAIL job"; }
}
