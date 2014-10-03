<?php

// @todo factor out tests which exist in both JQStore_ArrayTest and JQStore_PropelTest into JQStore_AllTest
// @todo factor out tests specific to JQStore_Array into JQStore_ArrayTest
// @todo factor out tests in here that are really JQStore_* tests int JQStore_AllTest -- this file should probably be JQManagedJob

require_once dirname(__FILE__) . '/TestCommon.php';

class JQJobsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Utility function to help bootstrap states for testing.
     */
    public static function moveJobToStatus($mJob, $targetStatus)
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

        foreach ($pathForSetup[$targetStatus] as $s) {
            $mJob->setStatus($s);
        }
    }

    private function setup10SampleJobs($q)
    {
        // Add jobs
        foreach (range(1,10) as $i) {
            $q->enqueue(new SampleJob());
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
            $q->enqueue(new SampleAsyncJob($this));
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false, 'enableJitter' => false));
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
            $q->enqueue(new SampleJob());
        }

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
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
            $q->enqueue(new SampleFailJob());
        }

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
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
        $q->enqueue(new SampleFailJob());

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
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
            $q->enqueue(new SampleJob());
        }

        $this->assertEquals(10, $q->count());
        $this->assertEquals(10, $q->count('test'));
        $this->assertEquals(10, $q->count('test', JQManagedJob::STATUS_QUEUED));

        SampleJobCounter::reset();

        // Start a worker to run 1 job
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 1, 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(1, SampleJobCounter::count());
        $this->assertEquals(9, $q->count('test'));

        // Start a worker to run 2 jobs
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => 2, 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(3, SampleJobCounter::count());
        $this->assertEquals(7, $q->count('test'));

        // Start a worker to run remaining
        $w = new JQWorker($q, array('queueName' => 'test', 'exitAfterNJobs' => NULL, 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(10, SampleJobCounter::count());
        $this->assertEquals(0, $q->count('test'));
    }

    /**
     * @testdox JQJobs does not attempt to coalesce a job with a NULL coalesceId
     */
    function testJobsWithNullCoalesceIdAreNotCoalesced()
    {
        $q = new JQStore_Array();

        $this->assertEquals(0, $q->count('test'));
        $q->enqueue(new SampleCoalescingJob(NULL));
        $q->enqueue(new SampleCoalescingJob(NULL));
        $this->assertEquals(2, $q->count('test'));
    }

    /**
     * @testdox JQJobs attempts to coalesce jobs if coalesceId is non-null
     */
    function testJobsWithNonNullCoalesceIdAreCoalesced()
    {
        $coalesceId = 1234;
        $q = new JQStore_Array();

        $this->assertEquals(0, $q->count('test'));
        $q->enqueue(new SampleCoalescingJob($coalesceId));
        $q->enqueue(new SampleCoalescingJob($coalesceId));
        $this->assertEquals(1, $q->count('test'));
    }
    /**
     * @testdox JQJobs queues a coalescing job normally if there is no existing coalesceId
     */
    function testCoalescingJobsEnqueueLikeNormalIfNoExistingJob()
    {
        $q = new JQStore_Array();

        $q->enqueue(new SampleCoalescingJob(4321));

        $this->assertEquals(1, $q->count('test'));
    }

    function testJobsAreRetrieveableByCoalesceId()
    {
        // Create a queue
        $q = new JQStore_Array();

        // Add a job
        $coalesceId = 'foo';
        $insertedJob = new SampleCoalescingJob($coalesceId);
        $q->enqueue($insertedJob);

        // Try to retrieve a job by coalesceId, make sure it's the same object
        $retrievedJob = $q->getByCoalesceId($coalesceId)->getJob();
        $this->assertEquals($insertedJob, $retrievedJob);
    }

    function testJobsAutoRetryOnFailure()
    {
        // create a queuestore
        $maxAttempts = 5;
        $q = new JQStore_Array();
        $mJob = $q->enqueue(new SampleFailJob(array('maxAttempts' => $maxAttempts)));

        // Start a worker to run the jobs.
        $w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
        $w->start();

        $this->assertEquals(0, $q->count('test', 'queued'));
        $this->assertEquals(1, $q->count('test', 'failed'));
        $this->assertEquals($maxAttempts, $mJob->getAttemptNumber());
    }

    /**
     * @dataProvider retryDataProvider
     */
    function testRetry($previousAttempts, $maxAttempts, $mulligan, $expectedStatus, $expectedMaxAttempts, $description)
    {
        if ($expectedMaxAttempts === NULL) $expectedMaxAttempts = $maxAttempts;

        // create a queuestore
        $q = new JQStore_Array();

        // set up initial condiitions
        $testJob = new JQTestJob(array('maxAttempts' => $maxAttempts));
        $mJob = new JQManagedJob($q, $testJob);
        JQJobsTest::moveJobToStatus($mJob, JQManagedJob::STATUS_QUEUED);
        $mJob->setAttemptNumber($previousAttempts);
        $mJob->markJobStarted();
        JQJobsTest::moveJobToStatus($mJob, JQManagedJob::STATUS_RUNNING);

        // fail job
        $mJob->markJobFailed('retry', $mulligan);

        $this->assertEquals($expectedStatus, $mJob->getStatus(), "Unexpected status");
        $this->assertEquals($expectedMaxAttempts, $mJob->getMaxAttempts(), "Unexpected maxAttempts");
    }
    function retryDataProvider()
    {
        $maxMulligans = JQManagedJob::MULLIGAN_MAX_ATTEMPTS;
        return array(
            //      previousAttempts    maxAttempts     mulligan        expectedStatus                  expectedMaxAttempts     description
            array(  0,                  1,              false,          JQManagedJob::STATUS_FAILED,    NULL,                   "normal failure after maxAttempts reached"),
            array(  0,                  2,              false,          JQManagedJob::STATUS_QUEUED,    NULL,                   "normal retry under maxAttempts tries"),
            array(  0,                  2,              true,           JQManagedJob::STATUS_QUEUED,    3,                      "normal mulligan retry"),
            array(  $maxMulligans-2,    $maxMulligans,  true,           JQManagedJob::STATUS_QUEUED,    $maxMulligans,          "last mulligan retry"),
            array(  $maxMulligans-1,    $maxMulligans,  true,           JQManagedJob::STATUS_FAILED,    $maxMulligans,          "failed -- too many mulligan retries"),
        );
    }

    /**
     * @dataProvider retryStateDataProvider
     */
    function testRetryWorksVsCurrentState($initialStatus, $expectedRetryOK)
    {
        // create a queuestore
        $q = new JQStore_Array();

        // set up initial condiitions
        $testJob = new JQTestJob();
        $mJob = new JQManagedJob($q, $testJob);
        JQJobsTest::moveJobToStatus($mJob, $initialStatus);

        if (!$expectedRetryOK)
        {
            $this->setExpectedException('JQManagedJob_InvalidStateChangeException');
        }
        $retryOK = $mJob->retry();
        if ($expectedRetryOK)
        {
            $this->assertTrue($retryOK, "Retry should have succeeded.");
            $this->assertEquals(JQManagedJob::STATUS_QUEUED, $mJob->getStatus(), "job should be queued again");
        }
    }

    function retryStateDataProvider()
    {
        return array(                               // INITIAL STATE                      RETRY SHOULD WORK
            'Unueued job can be retried'      => array(JQManagedJob::STATUS_UNQUEUED,     true),
            'Queued job can be retried'       => array(JQManagedJob::STATUS_QUEUED,       true),
            'Running job can be retried'      => array(JQManagedJob::STATUS_RUNNING,      true),
            'Wait Async job can be retried'   => array(JQManagedJob::STATUS_WAIT_ASYNC,   true),
            'Failed job can be retried'       => array(JQManagedJob::STATUS_FAILED,       true),
            'Completed job cannot be retried' => array(JQManagedJob::STATUS_COMPLETED,    false),
        );
    }

    /**
     * @testdox option priority_adjust defaults to NULL and proc_nice will not be called.
     */
    function testAdjustPriorityDefault()
    {
        $q = new JQStore_Array();

        // test no adjustment uses default
        $wMock = $this->getMock('JQWorker', array('adjustPriority'), array($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false)));
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
        $wMock = $this->getMock('JQWorker', array('adjustPriority'), array($q, array('adjustPriority' => 10, 'queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false)));
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
        // create a queuestore
        $q = new JQStore_Array();

        // set up initial condiitions
        $mJob = new JQManagedJob($q);
        JQJobsTest::moveJobToStatus($mJob, $from);
        $this->assertEquals($from, $mJob->getStatus());

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

    /**
     * @testdox maxRuntimeSeconds defaults to NULL (disabled)
     */
    function testDefaultMaxRuntimeSecondsIsNull()
    {
        $q = new JQStore_Array();
        $mJob = new JQManagedJob($q);
        $this->assertNull($mJob->getMaxRuntimeSeconds());
    }

    /**
     * @dataProvider failedJobDetectionDataProvider
     */
    function testFailedJobDetection($errorGeneratorF, $exceptionMessageContains)
    {
        // setup
        $q = new JQStore_Array();
        $mJob = new JQManagedJob($q, new SampleCallbackJob($errorGeneratorF));
        $mJob->setStatus(JQManagedJob::STATUS_QUEUED);
        $mJob->markJobStarted();

        // run
        $err = $mJob->run($mJob);
        $this->assertEquals($exceptionMessageContains, $err, "JQManagedJob::run() failed to detect {$exceptionMessageContains}");
    }
    function failedJobDetectionDataProvider()
    {
        // Handles: E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR => 4597
        return array(
            array(
                function() {
                    throw new JobTestException('JobTestException');
                },
                'JobTestException'
            ),
            array(
                function() {
                    trigger_error("E_USER_ERROR", E_USER_ERROR);
                },
                'E_USER_ERROR'
            ),
        );
    }

    /**
     * @dataProvider autoscalingAlgorithmsDataProvider
     */
    function testAutoscalingAlgorithms($algo, $numPending, $maxConcurrency, $expectedValue)
    {
        $this->assertEquals($expectedValue, JQAutoscaler::calculateScale($algo, $numPending, $maxConcurrency));
    }
    function autoscalingAlgorithmsDataProvider()
    {
        return array(
            //    algorithm         pending     max     expected
            array('linear',         0,          100,    0),
            array('linear',         1,          100,    1),
            array('linear',         50,         100,    50),
            array('linear',         100,        100,    100),
            array('linear',         101,        100,    100),
            array('linear',         500,        100,    100),
            array('halfLinear',     0,          100,    0),
            array('halfLinear',     1,          100,    1),
            array('halfLinear',     2,          100,    1),
            array('halfLinear',     199,        100,    99),
            array('halfLinear',     200,        100,    100),
            array('halfLinear',     201,        100,    100),
        );
    }
}

class JobTestException extends Exception {}
