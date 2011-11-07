<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQJobsTest extends PHPUnit_Framework_TestCase
{
    public $counter = 0;

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

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $this->counter);
        $this->assertEquals(0, $q->count('test'));
    }

    /**
     * @testdox Test worker doesn't block when a job returns JQManagedJob::STATUS_WAIT_ASYNC
     */
    function testJQJobsWaitAsync()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $this->assertEquals(0, $q->count('test'));

        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleAsyncJob($this), array('queueName' => 'test'));
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_WAIT_ASYNC));
    }

    /**
     * @testdox JQJobs deletes successfully deleted jobs
     */
    function testDeleteSuccessfulJobs()
    {
        // create a queuestore
        $q = new JQStore_Array();

        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob($this), array('queueName' => 'test'));
        }

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(0, $q->count('test'), "JQJobs didn't seem to automatically remove completed jobs.");
    }

    /**
     * @testdox JQJobs does not delete failed jobs
     */
    function testDoNotDeleteFailedJobs()
    {
        // create a queuestore
        $q = new JQStore_Array();

        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleFailJob($this), array('queueName' => 'test'));
        }

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $q->count('test'), "JQJobs didn't seem to leave intact failed jobs.");
    }

    /**
     * @testdox Test JQJobs catches fatal PHP errors during job execution and marks job as failed
     */
    function testJqJobsCatchesPHPErrorDuringJob()
    {
        // create a queuestore
        $q = new JQStore_Array();
        $q->enqueue(new SampleFailJob($this), array('queueName' => 'test'));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(0, $q->count('test', 'queued'));
        $this->assertEquals(1, $q->count('test', 'failed'));
    }

    /**
     * @testdox Worker option exitAfterNJobs: NULL means never exit, positive integer will exit after N jobs
     */
    function testWorkerOptionExitAfterNJobs()
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

        // Start a worker to run 1 job
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 1, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(1, $this->counter);
        $this->assertEquals(9, $q->count('test'));

        // Start a worker to run 2 jobs
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 2, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(3, $this->counter);
        $this->assertEquals(7, $q->count('test'));

        // Start a worker to run remaining
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => NULL, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, $this->counter);
        $this->assertEquals(0, $q->count('test'));
    }

    /**
     * @testdox JQJobs does not attempt to coalesce a job with a NULL coalesceId
     */
    function testJobsWithNullCoalesceIdAreNotCoalesced()
    {
        $qMock = $this->getMock('JQStore_Array', array('existsJobForCoalesceId'));
        $qMock->expects($this->never())
                            ->method('existsJobForCoalesceId')
                            ;

        $qMock->enqueue(new SampleCoalescingJob(NULL), array('queueName' => 'test'));
    }

    /**
     * @testdox JQJobs attempts to coalesce jobs if coalesceId is non-null
     */
    function testJobsWithNonNullCoalesceIdAreCoalesced()
    {
        $coalesceId = 1234;

        $qMock = $this->getMock('JQStore_Array', array('existsJobForCoalesceId'));
        $qMock->expects($this->once())
                            ->method('existsJobForCoalesceId')
                            ->with($coalesceId)
                            ;

        $qMock->enqueue(new SampleCoalescingJob($coalesceId), array('queueName' => 'test'));
    }
    /**
     * @testdox JQJobs queues a coalescing job normally if there is no existing coalesceId
     */
    function testCoalescingJobsEnqueueLikeNormalIfNoExistingJob()
    {
        $q = new JQStore_Array();

        $q->enqueue(new SampleJob($this, 1), array('queueName' => 'test'));

        $this->assertEquals(1, $q->count('test'));
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
        // Create a queue
        $q = new JQStore_Array();

        // Add a job
        $coalesceId = 'foo';
        $insertedJob = new SampleCoalescingJob($coalesceId);
        $options = array('queueName' => 'test');
        $q->enqueue($insertedJob, $options);

        // Try to retrieve a job by coalesceId, make sure it's the same object
        $retrievedJob = $q->getByCoalesceId($coalesceId)->getJob();
        $this->assertEquals($insertedJob, $retrievedJob);
    }

    function testRetry()
    {
        // create a queuestore
        $maxAttempts = 5;
        $q = new JQStore_Array();
        $jqjob = $q->enqueue(new SampleFailJob($this), array('queueName' => 'test', 'maxAttempts' => $maxAttempts));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(0, $q->count('test', 'queued'));
        $this->assertEquals(1, $q->count('test', 'failed'));
        $this->assertEquals($maxAttempts, $jqjob->getAttemptNumber());
    }
}
