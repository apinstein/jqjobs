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
        $this->con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME);
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

    /**
     * @testdox JQJobs does not queue another job if there is an existing coalesceId. The original job is returned from JQStore::enqueue()
     */
    function testCoalescingJobsCoalesceEnqueueingOfDuplicateJobs()
    {
        $q = new JQStore_Array();

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

}
