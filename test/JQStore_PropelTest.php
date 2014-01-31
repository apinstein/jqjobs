<?php

require_once dirname(__FILE__) . '/TestCommon.php';
require_once dirname(__FILE__) . '/JQStore_AllTest.php';

class JQStore_PropelTest extends JQStore_AllTest
{
    function setup()
    {
        try {
            $this->con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME);
        } catch (Exception $e) {
            die($e->getMessage());
        }
        $this->con->beginTransaction();
        $this->con->query("truncate jqstore_managed_job");
        $this->jqStore = new JQStore_Propel('JQStoreManagedJob', $this->con);
    }

    function tearDown()
    {
        $this->con->rollback();
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
            $q->enqueue(new QuietSimpleJob($i), array('queueName' => 'test'));
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $w->jobsProcessed());
        $this->assertEquals(0, $q->count('test'));
    }

    private function setup10SampleJobs($queueName = 'test')
    {
        $jobIdsByIndex = array();
        foreach (range(1,10) as $i) {
            $job = $this->jqStore->enqueue(new QuietSimpleJob($i), array('queueName' => $queueName));
            $jobIdsByIndex[] = $job->getJobId();
        }
        return $jobIdsByIndex;
    }

    function testGetJobWithMutexLocksJobSuccessfully()
    {
        $jobIdsByIndex = $this->setup10SampleJobs();
        $jobId = current($jobIdsByIndex);
        $j = $this->jqStore->getWithMutex($jobId);
        $this->setExpectedException('JQStore_JobIsLockedException');
        $this->jqStore->getWithMutex($jobId);
    }

    function testGetJobWithMutexThenClearThenLock()
    {
        $jobIdsByIndex = $this->setup10SampleJobs();
        $jobId = current($jobIdsByIndex);
        $this->jqStore->getWithMutex($jobId);
        $this->jqStore->clearMutex($jobId);
        $this->jqStore->getWithMutex($jobId);
    }

    function testJobsAreRetrieveableByCoalesceId()
    {
        // Add some jobs
        $coalesceId  = 'foo';
        $insertedJob = new SampleCoalescingJob($coalesceId);
        $options     = array('queueName' => 'test');
        $this->jqStore->enqueue($insertedJob, $options);

        // Make sure we have a job enqueued
        // Helpful for debugging...
        $this->assertEquals(1, $this->jqStore->count('test'));
        $this->assertEquals($coalesceId, $insertedJob->coalesceId());

        // Make sure the job exists for the coalesceId
        $retrievedJob = $this->jqStore->getByCoalesceId($coalesceId)->getJob();
        $this->assertEquals($insertedJob, $retrievedJob);
    }

    /**
     * @testdox JQWorker gracefully handles Exceptions thrown during unserialize()/__wakeup() of jobs by failing the job.
     */
    function testJqJobsCatchesUnserializeExceptions()
    {
        // create a queuestore
        $mJob = $this->jqStore->enqueue(new SampleExceptionalUnserializerJob("custom data"), array('queueName' => 'test'));
        $this->assertNotNull($mJob->getJob(), "Verifying that job is legit...");
        $this->assertEquals("custom data", $mJob->getJob()->data, "Verifying job data...");

        $mJobArray = $mJob->toArray();
        $serializedJob = $mJobArray['job'];

        // Start a worker to run the jobs.
        $w = new JQWorker($this->jqStore, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        // have to re-fetch job since db state changed...
        $mJob = $this->jqStore->get($mJob->getJobId());

        // we only get here if the worker cleanly exited.
        $this->assertEquals(JQManagedJob::STATUS_FAILED, $mJob->getStatus());

        // ensure that the "job" is still as expected...
        $this->assertEquals($serializedJob, $mJob->getJob(), "Serialized JQJob data has been adulterated.");

        // ensure that the failed message says something about de-serialization failure?
        $this->assertEquals(JQManagedJob::STATUS_FAILED, $mJob->getStatus(), "Job should be marked as failed.");
        $this->assertEquals("JQManagedJob.job is not a JQJob instance.", $mJob->getErrorMessage(), "Unexpected job error message.");
    }
}
