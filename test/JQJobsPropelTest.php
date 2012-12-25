<?php

/**
 * Right now this test is coupled to TourBuzz to get access to an actual JQStore_Propel. Need to figure out a way to factor this out later.
 * Maybe by including the propel JQStore_Propel classes in the JQJobs?
 */

require_once dirname(__FILE__) . '/TestCommon.php';

class JQJobsPropelTest extends PHPUnit_Framework_TestCase
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
        $this->q = new JQStore_Propel('JQStoreManagedJob', $this->con);
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
        $q = $this->q;
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
            $job = $this->q->enqueue(new QuietSimpleJob($i), array('queueName' => $queueName));
            $jobIdsByIndex[] = $job->getJobId();
        }
        return $jobIdsByIndex;
    }

    function testGetJobWithMutexLocksJobSuccessfully()
    {
        $jobIdsByIndex = $this->setup10SampleJobs();
        $jobId = current($jobIdsByIndex);
        $j = $this->q->getWithMutex($jobId);
        $this->setExpectedException('JQStore_JobIsLockedException');
        $this->q->getWithMutex($jobId);
    }

    function testGetJobWithMutexThenClearThenLock()
    {
        $jobIdsByIndex = $this->setup10SampleJobs();
        $jobId = current($jobIdsByIndex);
        $this->q->getWithMutex($jobId);
        $this->q->clearMutex($jobId);
        $this->q->getWithMutex($jobId);
    }

    /**
     * @testdox JQJobs does not queue another job if there is an existing coalesceId. The original job is returned from JQStore::enqueue()
     */
    function testCoalescingJobsCoalesceEnqueueingOfDuplicateJobs()
    {
        $q = $this->q;

        $coalesceId = 1234;

        $firstJobEnqueued = $q->enqueue(new SampleCoalescingJob($coalesceId), array('queueName' => 'test'));
        $this->assertEquals(1, $q->count('test'));

        $secondJobEnqueued = $q->enqueue(new SampleCoalescingJob($coalesceId), array('queueName' => 'test'));
        $this->assertEquals(1, $q->count('test'));
        $this->assertEquals($firstJobEnqueued, $secondJobEnqueued);
    }

    function testJobsAreRetrieveableByCoalesceId()
    {
        // Add some jobs
        $coalesceId  = 'foo';
        $insertedJob = new SampleCoalescingJob($coalesceId);
        $options     = array('queueName' => 'test');
        $this->q->enqueue($insertedJob, $options);

        // Make sure we have a job enqueued
        // Helpful for debugging...
        $this->assertEquals(1, $this->q->count('test'));
        $this->assertEquals($coalesceId, $insertedJob->coalesceId());

        // Make sure the job exists for the coalesceId
        $retrievedJob = $this->q->getByCoalesceId($coalesceId)->getJob();
        $this->assertEquals($insertedJob, $retrievedJob);
    }

    /**
     * @testdox JQWorker gracefully handles Exceptions thrown during unserialize()/__wakeup() of jobs by failing the job.
     */
    function testJqJobsCatchesUnserializeExceptions()
    {
        // create a queuestore
        $job = $this->q->enqueue(new SampleExceptionalUnserializerJob(), array('queueName' => 'test'));

        // Start a worker to run the jobs.
        $w = new JQWorker($this->q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => false));
        $w->start();

        // have to re-fetch job since db state changed...
        $job = $this->q->get($job->getJobId());

        // we only get here if the worker cleanly exited.
        $this->assertEquals(JQManagedJob::STATUS_FAILED, $job->getStatus());
    }
}
