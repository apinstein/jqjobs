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

    /**
     * OK here's the situation
     *
     * Our signal processing stuff is supposed to prevent jobs from being hung in a RUNNING state.
     *
     * The way this works is that essentially anytime a job is INTERRUPTED un-gracefully, we will just pretend that the job never ran.
     * Since it's marked as RUNNING in the DB, this means that we need to update the DB to show it as QUEUED (mulligan retry).
     *
     * Here's where it gets really ugly. If the signal fires after the data has been applied to the Propel instance, but before it's committed to the DB,
     * then it's possible for the software model to be differnt from the DB model in a way that makes the graceful signal handler think that the job's status
     * was successfully reported to the DB when it wasn't. 
     *
     * So the graceful signal handler needs to be sure to know what the DB thinks, and to re-queue the job if it's checked out as running.
     *
     * If propel's instance cache is hit for the job, then you risk the problem that the data from the "get job from DB" will be the not-yet-persisted status
     * which would foil the check described above from working. This test just ensures that this isn't broken.
     *
     */
    function testGracefulRetryDuringSplitBrain()
    {
        $q = $this->jqStore;

        // exit our transaction insanity since abort() inside of gracefullyRetryCurrentJob() needs to actually work
        $q->abort();
        $this->assertEquals(0, $q->count('test'), "Test database not empty; you should re-initialize the test db.");

        $mJob = $q->enqueue(new SampleFailJob($i), array('queueName' => 'test'));

        // mark job as running in DB to simulate a job that's running when interrupted
        $mJob->markJobStarted();    // will save to DB
        $this->assertEquals(0, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // mark job as FAILED on the PROPEL object to create split-brain (DB thinks RUNNING, MEMORY thinks FAILED)
        $dbJob = JQStoreManagedJobPeer::retrieveByPK($mJob->getJobId());
        $mJob->setStatus(JQManagedJob::STATUS_FAILED);
        $dbJob->setStatus(JQManagedJob::STATUS_FAILED);
        // attempt to gracefully retry; should mark as queued
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->gracefullyRetryCurrentJob($mJob);

        $this->assertEquals(1, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // cleanup
        $q->delete($mJob);
    }
}
