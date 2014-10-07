<?php

require_once dirname(__FILE__) . '/TestCommon.php';
require_once dirname(__FILE__) . '/JQStore_AllTest.php';

/**
 * @group JQStore_Propel
 * @todo Should we refactor most of these tests up into JQStore_AllTest?
 */
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
     * This is not an *all* test since serialization of jobs is a per-JQStore choice...
     * @testdox JQWorker gracefully handles Exceptions thrown during unserialize()/__wakeup() of jobs by failing the job.
     */
    function testJqJobsCatchesUnserializeExceptions()
    {
        $mJob = $this->jqStore->enqueue(new SampleExceptionalUnserializerJob("custom data"));
        $this->assertNotNull($mJob->getJob(), "Verifying that job is legit...");
        $this->assertEquals("custom data", $mJob->getJob()->data, "Verifying job data...");

        $mJobArray = $mJob->toArray();
        $serializedJob = $mJobArray['job'];

        // Start a worker to run the jobs.
        $w = new JQWorker($this->jqStore, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
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

        $mJob = $q->enqueue(new SampleFailJob());

        // mark job as running in DB to simulate a job that's running when interrupted
        $mJob->markJobStarted();    // will save to DB
        $this->assertEquals(0, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // mark job as FAILED on the PROPEL object to create split-brain (DB thinks RUNNING, MEMORY thinks FAILED)
        $dbJob = JQStoreManagedJobPeer::retrieveByPK($mJob->getJobId());
        $mJob->setStatus(JQManagedJob::STATUS_FAILED);
        $dbJob->setStatus(JQManagedJob::STATUS_FAILED);
        // attempt to gracefully retry; should mark as queued
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->gracefullyRetryCurrentJob($mJob);

        $this->assertEquals(1, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // cleanup
        $q->delete($mJob);
    }
}
