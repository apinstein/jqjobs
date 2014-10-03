<?php

abstract class JQStore_AllTest extends PHPUnit_Framework_TestCase
{
    // subclasses should configure a JQStore
    protected $jqStore;

    private function setup10SampleJobs()
    {
        // Add jobs
        $jobIdsByIndex = array();
        foreach (range(1,10) as $i) {
            $job = $this->jqStore->enqueue(new SampleJob());
            $jobIdsByIndex[] = $job->getJobId();
        }
        return $jobIdsByIndex;
    }

    private function setup10QuietSimpleJobs()
    {
        $jobIdsByIndex = array();
        foreach (range(1,10) as $i) {
            $job = $this->jqStore->enqueue(new QuietSimpleJob($i));
            $jobIdsByIndex[] = $job->getJobId();
        }
        return $jobIdsByIndex;
    }

    function testGetJobWithoutMutex()
    {
        $jobsById = $this->setup10SampleJobs();

        foreach ($jobsById as $index => $jobId) {
            $j = $this->jqStore->get($jobId);
            $this->assertEquals($jobId, $j->getJobId());
        }
    }

    function testGetJobWithMutexLocksJobSuccessfully()
    {
        $jobIdsByIndex = $this->setup10QuietSimpleJobs();
        $jobId = current($jobIdsByIndex);
        $j = $this->jqStore->getWithMutex($jobId);
        $this->setExpectedException('JQStore_JobIsLockedException');
        $this->jqStore->getWithMutex($jobId);
    }

    function testGetJobWithMutexThenClearThenLock()
    {
        $jobIdsByIndex = $this->setup10QuietSimpleJobs();
        $jobId = current($jobIdsByIndex);
        $this->jqStore->getWithMutex($jobId);
        $this->jqStore->clearMutex($jobId);
        $this->jqStore->getWithMutex($jobId);
    }

    /**
     * @testdox JQJobs does not queue another job if there is an existing coalesceId. The original job is returned from JQStore::enqueue()
     */
    function testCoalescingJobsCoalesceEnqueueingOfDuplicateJobs()
    {
        $coalesceId = 1234;

        $firstJobEnqueued = $this->jqStore->enqueue(new SampleCoalescingJob($coalesceId));
        $this->assertEquals(1, $this->jqStore->count('test'));

        $secondJobEnqueued = $this->jqStore->enqueue(new SampleCoalescingJob($coalesceId));
        $this->assertEquals(1, $this->jqStore->count('test'));
        $this->assertEquals($firstJobEnqueued, $secondJobEnqueued);
    }

    function testCountJobs()
    {
        $q = $this->jqStore;
        $this->assertEquals(0, $q->count('test'));
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob());
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
            $enqueuedJob = $q->enqueue(new SampleJob());
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
     * @dataProvider jobIsPastMaxRuntimeSecondsDataProvider
     */
    function testJobIsPastMaxRuntimeSeconds($maxRuntimeSeconds, $currentStatus, $startDts, $expectedResult, $description)
    {
        $q = $this->jqStore;
        $mJob = new JQManagedJob($q);
        $mJob->fromArray(array(
            'status'            => $currentStatus,
            'maxRuntimeSeconds' => $maxRuntimeSeconds,
            'startDts'          => new DateTime($startDts),
        ));
        $this->assertEquals($expectedResult, $mJob->isPastMaxRuntimeSeconds(), $description);
    }

    /**
     * @testdox get() throws JQStore_JobNotFoundException if job cannot be found
     */
    function testGetNotFound()
    {
        $this->setExpectedException('JQStore_JobNotFoundException');
        $this->jqStore->get(9999);
    }

    /**
     * @testdox getWithMutex() throws JQStore_JobNotFoundException if job cannot be found
     */
    function testGetMutexNotFound()
    {
        $this->setExpectedException('JQStore_JobNotFoundException');
        $this->jqStore->getWithMutex(9999);
    }

    /**
     * @dataProvider jobIsPastMaxRuntimeSecondsDataProvider
     */
    function testDetectHungJobs($maxRuntimeSeconds, $currentStatus, $startDts, $expectedMulligan, $description)
    {
        $q = $this->jqStore;
        $mJob = $q->enqueue(new QuietSimpleJob(1, array('maxRuntimeSeconds' => $maxRuntimeSeconds)));
        if ($currentStatus === JQManagedJob::STATUS_RUNNING)
        {
            $mJob->markJobStarted(new DateTime($startDts));
        }
        else
        {
            JQJobs_TestHelper::moveJobToStatus($mJob, $currentStatus);
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

    /**
     * @testdox Test Basic JQJobs Processing using JQStore_Propel
     */
    function testJQJobs()
    {
        $q = $this->jqStore;
        $this->assertEquals(0, $q->count('test'));

        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new QuietSimpleJob($i));
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(10, $w->jobsProcessed());
        $this->assertEquals(0, $q->count('test'));
    }

    function testJobsAreRetrieveableByCoalesceId()
    {
        // Add some jobs
        $coalesceId  = 'foo';
        $insertedJob = new SampleCoalescingJob($coalesceId);
        $this->jqStore->enqueue($insertedJob);

        // Make sure we have a job enqueued
        // Helpful for debugging...
        $this->assertEquals(1, $this->jqStore->count('test'));
        $this->assertEquals($coalesceId, $insertedJob->coalesceId());

        // Make sure the job exists for the coalesceId
        $retrievedJob = $this->jqStore->getByCoalesceId($coalesceId)->getJob();
        $this->assertEquals($insertedJob, $retrievedJob);
    }
}
