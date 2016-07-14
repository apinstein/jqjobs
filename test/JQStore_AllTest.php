<?php

abstract class JQStore_AllTest extends PHPUnit_Framework_TestCase
{
    // subclasses should configure a JQStore
    protected $jqStore;

    /**
     * Test helper to generate multiple jobs in multiple specifically named queues.
     * 
     * This is a lower-level function; there are some helpers for $jobClassOrGeneratorCallback
     *
     * @param JQStore
     * @param array The queues to create jobs on; ex: [NULL], ['a','b','b']
     * @param int The number of jobs to create on each entry in $queues
     * @param mixed Info required to generate new job instances to be enqueued.
     *              string: The name of a JQJob class
     *              callable: a callback function of prototype (JQJob) function($queueName, $iteration) where $iteration is 1..$jobsPerQueue
     */
    private function setupNQueuesWithMJobs(JQStore $q, $queues, $jobsPerQueue, $jobClassOrGeneratorCallback = NULL)
    {
        $enqueuedJobs = [];

        $this->assertEquals(0, $q->count());

        // Add jobs
        foreach ($queues as $queueName) {
            for ($i = 0; $i < $jobsPerQueue; $i++) {
                $jobToEnqueue = NULL;
                if (is_callable($jobClassOrGeneratorCallback))
                {
                    $jobToEnqueue = $jobClassOrGeneratorCallback($queueName, $i);
                }
                else if (class_exists($jobClassOrGeneratorCallback))
                {
                    $jobToEnqueue = new $jobClassOrGeneratorCallback;
                }
                if (!$jobToEnqueue instanceof JQJob)
                {
                    throw new Exception("JQJob required.");
                }
                $enqueuedJobs[] = $q->enqueue($jobToEnqueue);
            }
        }

        $this->assertEquals(count($queues) * $jobsPerQueue, $q->count());
        foreach ($queues as $queueName) {
            $this->assertEquals($jobsPerQueue, $q->count($queueName, JQManagedJob::STATUS_QUEUED));
        }

        return $enqueuedJobs;
    }

    private static function generateQuietSimpleJob($queueName, $i)
    {
        return new QuietSimpleJob($i, [ 'queueName' => $queueName ]);
    }

    private function setup10SampleJobs()
    {
        $q = $this->jqStore;
        $jobIdsByIndex = array();

        $enqueuedJobs = $this->setupNQueuesWithMJobs($q, [NULL], 10, 'SampleJob');
        foreach ($enqueuedJobs as $job) {
            $jobIdsByIndex[] = $job->getJobId();
        }

        return $jobIdsByIndex;
    }

    private function setup10QuietSimpleJobs()
    {
        $q = $this->jqStore;
        $jobIdsByIndex = array();

        $enqueuedJobs = $this->setupNQueuesWithMJobs($q, [NULL], 10, ['JQStore_AllTest', 'generateQuietSimpleJob']);
        foreach ($enqueuedJobs as $job) {
            $jobIdsByIndex[] = $job->getJobId();
        }

        return $jobIdsByIndex;
    }

    /**
     * @testdox JQStore::get()
     */
    function testGetJob()
    {
        $jobsById = $this->setup10SampleJobs();

        foreach ($jobsById as $index => $jobId) {
            $j = $this->jqStore->get($jobId);
            $this->assertEquals($jobId, $j->getJobId());
        }
    }

    /**
     * @testdox JQStore::getWithMutex() returns a JQManagedJob
     */
    function testGetJobWithMutexReturnsJQManagedJob()
    {
        $jobIdsByIndex = $this->setup10QuietSimpleJobs();
        $jobId = current($jobIdsByIndex);
        $j = $this->jqStore->getWithMutex($jobId);
        $this->assertTrue($j instanceof JQManagedJob);
    }

    /**
     * @testdox JQStore::getWithMutex() throws JQStore_JobNotFoundException if the mutex cannot be acquired due to missing job.
     */
    function testGetJobWithMutexInCaseOfMissingJob()
    {
        $this->setExpectedException('JQStore_JobNotFoundException');
        $mJob = $this->jqStore->getWithMutex(JQJOB_ID_DOES_NOT_EXIST); // some non-existant job id
    }

    /**
     * @testdox JQStore::getWithMutex() throws JQStore_JobIsLockedException if job already locked
     */
    function testGetJobWithMutexLocksJobSuccessfully()
    {
        $jobIdsByIndex = $this->setup10QuietSimpleJobs();
        $jobId = current($jobIdsByIndex);
        $j = $this->jqStore->getWithMutex($jobId);
        $this->setExpectedException('JQStore_JobIsLockedException');
        $this->jqStore->getWithMutex($jobId);
    }

    /**
     * @testdox JQStore::clearMutex() removes the mutex
     */
    function testGetJobWithMutexThenClearThenLock()
    {
        $jobIdsByIndex = $this->setup10QuietSimpleJobs();
        $jobId = current($jobIdsByIndex);
        $this->jqStore->getWithMutex($jobId);
        $this->jqStore->clearMutex($jobId);
        $this->jqStore->getWithMutex($jobId);
    }

    /**
     * @testdox JQStore::clearMutex() is idempotent
     */
    function testClearMutexIsIdempotent()
    {
        $jobIdsByIndex = $this->setup10QuietSimpleJobs();
        $jobId = current($jobIdsByIndex);
        $this->jqStore->getWithMutex($jobId);
        $this->jqStore->clearMutex($jobId);
        $this->jqStore->clearMutex($jobId);
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
     * @testdox JQManagedJob::isPastMaxRuntimeSeconds()
     */
    function testJobIsPastMaxRuntimeSeconds($maxRuntimeSeconds, $currentStatus, $startDts, $expectedResult)
    {
        $q = $this->jqStore;
        $mJob = new JQManagedJob($q);
        $mJob->fromArray(array(
            'status'            => $currentStatus,
            'maxRuntimeSeconds' => $maxRuntimeSeconds,
            'startDts'          => new DateTime($startDts),
        ));
        $this->assertEquals($expectedResult, $mJob->isPastMaxRuntimeSeconds());
    }

    /**
     * @dataProvider jobIsPastMaxRuntimeSecondsDataProvider
     */
    function testDetectHungJobs($maxRuntimeSeconds, $currentStatus, $startDts, $expectedMulligan)
    {
        $q = $this->jqStore;
        $mJob = $q->enqueue(new QuietSimpleJob(1, array('maxRuntimeSeconds' => $maxRuntimeSeconds)));
        if ($currentStatus === JQManagedJob::STATUS_RUNNING)
        {
            $mJob->markJobStarted(new DateTime($startDts));
        }
        else if ($currentStatus === JQManagedJob::STATUS_WAIT_ASYNC)
        {
            $mJob->markJobStarted(new DateTime($startDts));
            JQJobs_TestHelper::moveJobToStatus($mJob, $currentStatus);
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
            //            maxRuntimeSeconds     current state                       start dts (relative to now)         expectedResult
            'maxRuntimeSeconds of NULL means never expire' =>
                    array(NULL,                 JQManagedJob::STATUS_RUNNING,       '-1 year',                          false),
            #'maxRuntimeSeconds does not apply to STATUS_UNQUEUED' =>
            #array(10,                   JQManagedJob::STATUS_UNQUEUED,      '-1 year',                          false),
            'maxRuntimeSeconds does not apply to STATUS_QUEUED' =>
                    array(10,                   JQManagedJob::STATUS_QUEUED,        '-1 year',                          false),
            'maxRuntimeSeconds does not apply to STATUS_COMPLETED' =>
                    array(10,                   JQManagedJob::STATUS_COMPLETED,     '-1 year',                          false),
            'maxRuntimeSeconds does not apply to STATUS_FAILED' =>
                    array(10,                   JQManagedJob::STATUS_FAILED,        '-1 year',                          false),
            'STATUS_RUNNING job- very expired job is past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_RUNNING,       '-1 year',                          true),
            'STATUS_RUNNING job - expired by 1 second is past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_RUNNING,       '-11 seconds',                      true),
            'STATUS_RUNNING job - at expiration is NOT past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_RUNNING,       '-10 seconds',                      false),
            'STATUS_RUNNING job - before expiration is NOT past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_RUNNING,       '-9 seconds',                       false),
            'STATUS_WAIT_ASYNC job - very expired job is past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_WAIT_ASYNC,    '-1 year',                          true),
            'STATUS_WAIT_ASYNC job - expired by 1 second is past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_WAIT_ASYNC,    '-11 seconds',                      true),
            'STATUS_WAIT_ASYNC job - at expiration is NOT past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_WAIT_ASYNC,    '-10 seconds',                      false),
            'STATUS_WAIT_ASYNC job - before expiration is NOT past maxRuntimeSeconds' =>
                    array(10,                   JQManagedJob::STATUS_WAIT_ASYNC,    '-9 seconds',                       false),
        );
    }


    /**
     * @testdox get() throws JQStore_JobNotFoundException if job cannot be found
     */
    function testGetNotFound()
    {
        $this->setExpectedException('JQStore_JobNotFoundException');
        $this->jqStore->get(JQJOB_ID_DOES_NOT_EXIST);
    }

    /**
     * @testdox Test Basic JQJobs Processing
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

    /**
     * @testdox Test JQStore->next(NULL) finds next job available on all queues
     */
    function testNextWithNoNamedQueues()
    {
        $q = $this->jqStore;
        $this->setupNQueuesWithMJobs($q, ['a', 'b', 'c'], 1, ['JQStore_AllTest','generateQuietSimpleJob']);

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(3, $w->jobsProcessed());
        $this->assertEquals(0, $q->count());
    }

    /**
     * @testdox Test JQStore->next('a') finds next job available on queue 'a' only
     */
    function testNextWithOneNamedQueue()
    {
        $q = $this->jqStore;
        $this->setupNQueuesWithMJobs($q, ['a', 'b', 'c'], 1, ['JQStore_AllTest','generateQuietSimpleJob']);

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'a', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(1, $w->jobsProcessed());
        $this->assertEquals(2, $q->count());
        $this->assertEquals(0, $q->count('a'));
        $this->assertEquals(1, $q->count('b'));
        $this->assertEquals(1, $q->count('c'));
    }

    /**
     * @testdox Test JQStore->next('a,b') finds next job available on queues 'a' or 'b' 
     */
    function testNextWithMultipleNamedQueues()
    {
        $q = $this->jqStore;
        $this->setupNQueuesWithMJobs($q, ['a', 'b', 'c'], 1, ['JQStore_AllTest','generateQuietSimpleJob']);

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'a,b', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(2, $w->jobsProcessed());
        $this->assertEquals(1, $q->count());
        $this->assertEquals(0, $q->count('a'));
        $this->assertEquals(0, $q->count('b'));
        $this->assertEquals(1, $q->count('c'));
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

    function testGetByCoalesceIdWhenNoJobExists()
    {
        $this->assertNull($this->jqStore->getByCoalesceId('doesnotexist'));
    }
}
