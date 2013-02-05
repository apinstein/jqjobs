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
     * @testdox Worker WorkFactor filtering
     * @dataProvider workerOnlyRunsJobsWithAcceptableWorkFactorDataProvider
     */
    function testWorkerOnlyRunsJobsWithAcceptableWorkFactor($minWorkFactor, $maxWorkFactor, $expectedJobsThatRanCount)
    {
        $this->assertEquals(0, $this->jqStore->count('test'));

        // Add jobs
        foreach (range(1,10) as $i) {
            $this->jqStore->enqueue(new SampleJob($this), array('queueName' => 'test', 'workFactor' => $i));
        }

        $this->assertEquals(10, $this->jqStore->count());
        $this->assertEquals(10, $this->jqStore->count('test'));
        $this->assertEquals(10, $this->jqStore->count('test', JQManagedJob::STATUS_QUEUED));

        SampleJobCounter::reset();

        // Start a worker to run 1 job
        $w = new JQWorker($this->jqStore, array('queueName' => 'test', 'minWorkFactor' => $minWorkFactor, 'maxWorkFactor' => $maxWorkFactor, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals($expectedJobsThatRanCount, SampleJobCounter::count());
        $this->assertEquals(10-$expectedJobsThatRanCount, $this->jqStore->count('test'));
    }
    function workerOnlyRunsJobsWithAcceptableWorkFactorDataProvider()
    {
        return array(
            //      minWorkFactor   maxWorkFactor   expectedRunCount
            array(  NULL,           NULL,           10),
            array(  0,              10,             10),
            array(  0,              5,              5),
            array(  6,              NULL,           5),
        );
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
