<?php

abstract class JQStore_AllTest extends PHPUnit_Framework_TestCase
{
    // subclasses should configure a JQStore
    protected $jqStore;

    /**
     * @testdox JQJobs does not queue another job if there is an existing coalesceId. The original job is returned from JQStore::enqueue()
     */
    function testCoalescingJobsCoalesceEnqueueingOfDuplicateJobs()
    {
        $coalesceId = 1234;

        $firstJobEnqueued = $this->jqStore->enqueue(new SampleCoalescingJob($coalesceId), array('queueName' => 'test'));
        $this->assertEquals(1, $this->jqStore->count('test'));

        $secondJobEnqueued = $this->jqStore->enqueue(new SampleCoalescingJob($coalesceId), array('queueName' => 'test'));
        $this->assertEquals(1, $this->jqStore->count('test'));
        $this->assertEquals($firstJobEnqueued, $secondJobEnqueued);
    }

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

        SampleJobCounter::reset();

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, SampleJobCounter::count());
        $this->assertEquals(0, $q->count('test'));
    }
}
