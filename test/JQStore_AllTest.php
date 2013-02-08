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

    function testCountJobs()
    {
        $q = $this->jqStore;
        $this->assertEquals(0, $q->count('test'));
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob($this), array('queueName' => 'test'));
        }
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));
        $this->assertEquals(0, $q->count('test', JQManagedJob::STATUS_RUNNING));
    }

    function testEnumerateJobs()
    {
        $q = $this->jqStore;
        $found = array();
        foreach (range(1,10) as $i) {
            $enqueuedJob = $q->enqueue(new SampleJob($this), array('queueName' => 'test'));
            $found[$enqueuedJob->getJobId()] = false;
        }
        $foundCount = 0;
        foreach ($q->jobs() as $j) {
            // make sure id is found exactly once
            $this->assertArrayHasKey($j->getJobId(), $found, "Enumerated job shouldn't exist.");
            $this->assertFalse($found[$j->getJobId()]);
            $found[$j->getJobId()] = true;
            $foundCount++;
        }
        $this->assertEquals(10, $foundCount);
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

    /**
     * @dataProvider jobIsPastMaxRuntimeSecondsDataProvider
     */
    function testJobIsPastMaxRuntimeSeconds($maxRuntimeSeconds, $currentStatus, $startDts, $expectedResult, $description)
    {
        $mJob = new JQManagedJob($q);
        $mJob->fromArray(array(
            'status'            => $currentStatus,
            'maxRuntimeSeconds' => $maxRuntimeSeconds,
            'startDts'          => new DateTime($startDts),
        ));
        $this->assertEquals($expectedResult, $mJob->isPastMaxRuntimeSeconds(), $description);
    }

    /**
     * @dataProvider jobIsPastMaxRuntimeSecondsDataProvider
     */
    function testDetectHungJobs($maxRuntimeSeconds, $currentStatus, $startDts, $expectedMulligan, $description)
    {
        $q = $this->jqStore;
        $mJob = $q->enqueue(new QuietSimpleJob(1), array('queueName' => 'test', 'maxRuntimeSeconds' => $maxRuntimeSeconds));
        if ($currentStatus === JQManagedJob::STATUS_RUNNING)
        {
            $mJob->markJobStarted(new DateTime($startDts));
        }
        else
        {
            JQJobsTest::moveJobToStatus($mJob, $currentStatus);
        }
        $mJob->save();

        // verify initial conditions; one job, and in expected status
        $this->assertEquals(1, $q->count('test'), "Should only be 1 job in test queue for this test.");
        $this->assertEquals(1, $q->count('test', $currentStatus), "Should be one job in test queue with status {$currentStatus}...");
        $this->assertEquals(1, $mJob->getMaxAttempts());

        $q->detectHungJobs();
        // reload job; it was changed possibly in another connection
        $mJob = $q->get($mJob->getJobId());

        if ($expectedMulligan)
        {
            $this->assertEquals(2, $mJob->getMaxAttempts());
            $this->assertEquals(0, $q->count('test', JQManagedJob::STATUS_RUNNING), "There should be no jobs left running after detectHungJobs.");
            $this->assertEquals(1, $q->count('test', JQManagedJob::STATUS_QUEUED), "The hung job should've been requeued but can't be detected.");
        }
        else
        {
            $this->assertEquals(1, $mJob->getMaxAttempts());
            $this->assertEquals(1, $q->count('test', $currentStatus), "The job is considered not hung; it should still be in original status {$currentStatus}.");
        }
    }

    function jobIsPastMaxRuntimeSecondsDataProvider()
    {
        return array(
            //    maxRuntimeSeconds     current state                       start dts (relative to now)         expectedResult    description
            array(NULL,                 JQManagedJob::STATUS_RUNNING,       '-1 year',                          false,            'maxRuntimeSeconds of NULL means never expire'),
            #array(10,                   JQManagedJob::STATUS_UNQUEUED,      '-1 year',                          false,            'maxRuntimeSeconds does not apply to STATUS_UNQUEUED'),
            array(10,                   JQManagedJob::STATUS_QUEUED,        '-1 year',                          false,            'maxRuntimeSeconds does not apply to STATUS_QUEUED'),
            array(10,                   JQManagedJob::STATUS_WAIT_ASYNC,    '-1 year',                          false,            'maxRuntimeSeconds does not apply to STATUS_WAIT_ASYNC'),
            array(10,                   JQManagedJob::STATUS_COMPLETED,     '-1 year',                          false,            'maxRuntimeSeconds does not apply to STATUS_COMPLETED'),
            array(10,                   JQManagedJob::STATUS_FAILED,        '-1 year',                          false,            'maxRuntimeSeconds does not apply to STATUS_FAILED'),
            array(10,                   JQManagedJob::STATUS_RUNNING,       '-1 year',                          true,             'very expired job'),
            array(10,                   JQManagedJob::STATUS_RUNNING,       '-11 seconds',                      true,             'expired by 1 second'),
            array(10,                   JQManagedJob::STATUS_RUNNING,       '-10 seconds',                      false,            'at expiration'),
            array(10,                   JQManagedJob::STATUS_RUNNING,       '-9 seconds',                       false,            'before expiration'),
        );
    }

}
