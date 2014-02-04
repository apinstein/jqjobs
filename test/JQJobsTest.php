<?php

// @todo factor out tests which exist in both JQStore_Array_Test and JQStore_Propel_Test into JQStore_All_Test
// @todo factor out tests specific to JQStore_Array into JQStore_Array_Test

require_once dirname(__FILE__) . '/TestCommon.php';

class JQJobsTest extends PHPUnit_Framework_TestCase
{
    private function setup10SampleJobs($q, $queueName = 'test')
    {
        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob($this), array('queueName' => $queueName));
        }
    }

    function testGetJobWithoutMutex()
    {
        $q = new JQStore_Array();
        $this->setup10SampleJobs($q);

        foreach (range(1,10) as $i) {
            $j = $q->get($i);
        }
    }

    function testGetJobWithMutexLocksJobSuccessfully()
    {
        $q = new JQStore_Array();
        $this->setup10SampleJobs($q);

        $jobId = 1;
        $j = $q->getWithMutex($jobId);
        $this->setExpectedException('JQStore_JobIsLockedException');
        $q->getWithMutex($jobId);
    }

    function testGetJobWithMutexThenClearThenLock()
    {
        $q = new JQStore_Array();
        $this->setup10SampleJobs($q);

        $jobId = 1;
        $q->getWithMutex($jobId);
        $q->clearMutex($jobId);
        $q->getWithMutex($jobId);
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

        SampleJobCounter::reset();

        // Start a worker to run 1 job
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 1, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(1, SampleJobCounter::count());
        $this->assertEquals(9, $q->count('test'));

        // Start a worker to run 2 jobs
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 2, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(3, SampleJobCounter::count());
        $this->assertEquals(7, $q->count('test'));

        // Start a worker to run remaining
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => NULL, 'exitIfNoJobs' => true, 'silent' => true));
        $w->start();

        $this->assertEquals(10, SampleJobCounter::count());
        $this->assertEquals(0, $q->count('test'));
    }

    function testJQWorkerDoesNotAllowMaxWorkFactorLessThanMinWorkFactor()
    {
        $this->setExpectedException('Exception');
        $w = new JQWorker($q, array('minWorkFactor' => 6, 'maxWorkFactor' => 5));
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

    function testJobsAutoRetryOnFailure()
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

    /**
     * @testdox option priority_adjust defaults to NULL and proc_nice will not be called.
     */
    function testAdjustPriorityDefault()
    {
        $q = new JQStore_Array();

        // test no adjustment uses default
        $wMock = $this->getMock('JQWorker', array('adjustPriority'), array($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true)));
        $wMock->expects($this->never())
                            ->method('adjustPriority')
                            ;
        $wMock->start();
    }

    /**
     * @testdox option priority_adjust will call proc_nice to adjust the worker's priorty when queue is started.
     */
    function testAdjustPriorityOption()
    {
        $q = new JQStore_Array();

        // test with adjustment
        $wMock = $this->getMock('JQWorker', array('adjustPriority'), array($q, array('adjustPriority' => 10, 'queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true)));
        $wMock->expects($this->once())
                            ->method('adjustPriority')
                            ->with(10)
                            ;
        $wMock->start();
    }

    /**
     * @dataProvider stateTransitionsDataProvider
     */
    function testStateTransitions($from, $to, $expectedOk)
    {
        // map on how to bootstrap a job to the "FROM" state
        $pathForSetup = array(
            JQManagedJob::STATUS_UNQUEUED       => array(),
            JQManagedJob::STATUS_QUEUED         => array(JQManagedJob::STATUS_QUEUED),
            JQManagedJob::STATUS_RUNNING        => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING),
            JQManagedJob::STATUS_WAIT_ASYNC     => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING, JQManagedJob::STATUS_WAIT_ASYNC),
            JQManagedJob::STATUS_COMPLETED      => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING, JQManagedJob::STATUS_COMPLETED),
            JQManagedJob::STATUS_FAILED         => array(JQManagedJob::STATUS_QUEUED, JQManagedJob::STATUS_RUNNING, JQManagedJob::STATUS_FAILED),
        );

        // create a queuestore
        $q = new JQStore_Array();

        // set up initial condiitions
        $mJob = new JQManagedJob($q);
        foreach ($pathForSetup[$from] as $s) {
            $mJob->setStatus($s);
        }
        $this->assertEquals($from, $mJob->getStatus());
        // initial cond OK

        $transition = "[" . ($expectedOk ? 'OK' : 'NO') . "] {$from} => {$to}";
        if ($expectedOk)
        {
            $mJob->setStatus($to);
            $this->assertEquals($to, $mJob->getStatus(), "{$transition} should be allowed but failed.");
        }
        else
        {
            try {
                $mJob->setStatus($to);
                $this->fail("Expected JQManagedJob->setStatus() to throw Exception due to illegal state change.");
            } catch (Exception $e) {
                $this->assertEquals($from, $mJob->getStatus(), "Unallowed {$transition} still mutated job status.");
            }
        }
    }
    function stateTransitionsDataProvider()
    {
        $allStates = array(
            JQManagedJob::STATUS_UNQUEUED,
            JQManagedJob::STATUS_QUEUED,
            JQManagedJob::STATUS_RUNNING,
            JQManagedJob::STATUS_WAIT_ASYNC,
            JQManagedJob::STATUS_COMPLETED,
            JQManagedJob::STATUS_FAILED,
        );
        $legitTransitions = array(
            JQManagedJob::STATUS_UNQUEUED . "=>" . JQManagedJob::STATUS_QUEUED,
             JQManagedJob::STATUS_QUEUED . "=>" . JQManagedJob::STATUS_RUNNING,
              JQManagedJob::STATUS_RUNNING . "=>" . JQManagedJob::STATUS_COMPLETED,
              JQManagedJob::STATUS_RUNNING . "=>" . JQManagedJob::STATUS_FAILED,
              JQManagedJob::STATUS_RUNNING . "=>" . JQManagedJob::STATUS_QUEUED,
               JQManagedJob::STATUS_FAILED . "=>" . JQManagedJob::STATUS_QUEUED,
              JQManagedJob::STATUS_RUNNING . "=>" . JQManagedJob::STATUS_WAIT_ASYNC,
               JQManagedJob::STATUS_WAIT_ASYNC . "=>" . JQManagedJob::STATUS_WAIT_ASYNC,
                JQManagedJob::STATUS_WAIT_ASYNC . "=>" . JQManagedJob::STATUS_RUNNING,
                JQManagedJob::STATUS_WAIT_ASYNC . "=>" . JQManagedJob::STATUS_COMPLETED,
                JQManagedJob::STATUS_WAIT_ASYNC . "=>" . JQManagedJob::STATUS_FAILED,
                JQManagedJob::STATUS_WAIT_ASYNC . "=>" . JQManagedJob::STATUS_QUEUED,
        );
        $testCases = array();
        foreach ($allStates as $from) {
            foreach ($allStates as $to) {
                // skip set same
                if ($from === $to) continue;

                $transition = "{$from}=>{$to}";
                $expectedOk = in_array($transition, $legitTransitions);
                $testCases[] = array($from, $to, $expectedOk);
            }
        }
        return $testCases;
    }
}
