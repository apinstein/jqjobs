<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQManagedJobTest extends PHPUnit_Framework_TestCase
{
    /**
     * @testdox JQManagedJob's "job" property is immutable (cannot change jobs post-construction)
     * @todo then why do we have a setJob() function?
     */
    function testExceptionWhenAskedToManageASecondJob()
    {
        $this->setExpectedException('JQManagedJob_AlreadyHasAJobException');

        $mJob = new JQManagedJob(NULL, new JQTestJob()); // inject a job
        $mJob->setJob(new JQTestJob()); // then set another job (boom!)
    }

    /**
     * @dataProvider resolveAsyncToStateThrowsIfJobNotWaitAsync
     * @testdox JQManagedJob::resolveWaitAsyncJob() 
     *
     */
    function testResolveAsyncToStateThrowsIfJobNotWaitAsync($currentState, $expectedOk)
    {
        // create a queuestore
        $q = new JQStore_Array();

        $testJob = new SampleAsyncJob($this);
        $mJob = $q->enqueue($testJob);
        $mJob->markJobStarted();
        JQJobs_TestHelper::moveJobToStatus($mJob, $currentState);

        if ($expectedOk)
        {
            JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), JQManagedJob::STATUS_COMPLETED);
            $this->assertEquals(JQManagedJob::STATUS_COMPLETED, $mJob->getStatus());
        }
        else
        {
            try {
                JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), JQManagedJob::STATUS_COMPLETED);
                $this->fail("Expected JQManagedJob_InvalidStateException to be thrown.");
            } catch (JQManagedJob_InvalidStateException $e) {
                $this->assertEquals($currentState, $e->getJobStatus());
            }
        }
    }
    function resolveAsyncToStateThrowsIfJobNotWaitAsync()
    {
        return array(
            "will throw JQManagedJob_InvalidStateException if job is STATUS_QUEUED"     => array(JQManagedJob::STATUS_QUEUED,       false),
            "will throw JQManagedJob_InvalidStateException if job is STATUS_RUNNING"    => array(JQManagedJob::STATUS_RUNNING,      false),
            "will run if job is STATUS_WAIT_ASYNC"                                      => array(JQManagedJob::STATUS_WAIT_ASYNC,   true),
            "will throw JQManagedJob_InvalidStateException if job is STATUS_COMPLETED"  => array(JQManagedJob::STATUS_COMPLETED,    false),
            "will throw JQManagedJob_InvalidStateException if job is STATUS_FAILED"     => array(JQManagedJob::STATUS_FAILED,       false),
        );
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob()
     * @dataProvider resolveAsyncToStateDataProvider
     */
    function testResolveAsyncToState($resolvesToState, $expectedState)
    {
        // create a queuestore
        $q = new JQStore_Array();

        $mJob = $q->enqueue(new SampleAsyncJob($this));
        $mJob->markJobStarted();
        $mJob->run($mJob);
        $this->assertEquals(JQManagedJob::STATUS_WAIT_ASYNC, $mJob->getStatus());

        JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), $resolvesToState);
        $this->assertEquals($expectedState, $mJob->getStatus());
    }
    function resolveAsyncToStateDataProvider()
    {
        return array(
            'if $job->resolveWaitAsyncJob() returns disposition COMPLETE,   JQManagedJob marks the job complete'      => array(JQManagedJob::STATUS_COMPLETED,    JQManagedJob::STATUS_COMPLETED),
            'if $job->resolveWaitAsyncJob() returns disposition FAILED,     JQManagedJob marks the job failed'        => array(JQManagedJob::STATUS_FAILED,       JQManagedJob::STATUS_FAILED),
            'if $job->resolveWaitAsyncJob() returns disposition WAIT_ASYNC  JQManagedJob marks the job wait_async'    => array(JQManagedJob::STATUS_WAIT_ASYNC,   JQManagedJob::STATUS_WAIT_ASYNC),
        );
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob($q, $id, $data, true) will mark a job as failed when it throws an exception and convertExceptionToFailure=true
     */
    function testResolveAsyncExceptionMarksJobFailedWhenConvertExceptionsToFailuresIsEnabled()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $mJob = $q->enqueue(new SampleAsyncJob($this));
        $mJob->markJobStarted();
        $mJob->run($mJob);
        $this->assertEquals(JQManagedJob::STATUS_WAIT_ASYNC, $mJob->getStatus());

        JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), 'SampleAsyncJob_ResolveException', true);
        $this->assertEquals(JQManagedJob::STATUS_FAILED, $mJob->getStatus());
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob($q, $id, $data) will leave a job in wait_async when it throws an exception and convertExceptionToFailure=false (default)
     */
    function testResolveAsyncExceptionLeavesInWaitAsyncByDefault()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $mJob = $q->enqueue(new SampleAsyncJob($this));
        $mJob->markJobStarted();
        $mJob->run($mJob);
        $this->assertEquals(JQManagedJob::STATUS_WAIT_ASYNC, $mJob->getStatus());

        $this->setExpectedException('SampleAsyncJob_ResolveException');
        JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), 'SampleAsyncJob_ResolveException');
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob() throws JQStore_JobNotFoundException if job no longer exists.
     */
    function testResolveAsyncJobDoesNotExistThrowsException()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $this->setExpectedException('JQStore_JobNotFoundException');
        JQManagedJob::resolveWaitAsyncJob($q, 9999, array());
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob() wraps the $job->resolveWaitAsyncJob() in a mutex to prevent concurrency errors
     */
    function testResolveAsyncJobUsesMutex()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $mJob = $q->enqueue(new SampleAsyncJob($this));
        $mJob->markJobStarted();
        $mJob->run($mJob);
        $this->assertEquals(JQManagedJob::STATUS_WAIT_ASYNC, $mJob->getStatus());

        $q->getWithMutex($mJob->getJobId());
        $this->setExpectedException('JQStore_JobIsLockedException');
        JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), array());
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob() clears mutex when job is marked failed.
     */
    function testResolveAsyncJobClearsMutexOnFailure()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $mJob = $q->enqueue(new SampleAsyncJob($this));
        $mJob->markJobStarted();
        $mJob->run($mJob);
        $this->assertEquals(JQManagedJob::STATUS_WAIT_ASYNC, $mJob->getStatus());

        JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), JQManagedJob::STATUS_FAILED);
        $this->assertEquals($mJob, $q->getWithMutex($mJob->getJobId()));
    }

    /**
     * @testdox JQManagedJob::resolveWaitAsyncJob() clears mutex when job's resolveWaitAsyncJob() throws an Exception.
     */
    function testResolveAsyncJobClearsMutexOnException()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $mJob = $q->enqueue(new SampleAsyncJob($this));
        $mJob->markJobStarted();
        $mJob->run($mJob);
        $this->assertEquals(JQManagedJob::STATUS_WAIT_ASYNC, $mJob->getStatus());

        try {
            JQManagedJob::resolveWaitAsyncJob($q, $mJob->getJobId(), 'SampleAsyncJob_ResolveException');
        } catch (SampleAsyncJob_ResolveException $e) {
            $this->assertEquals($mJob, $q->getWithMutex($mJob->getJobId()));
            return;
        }
        $this->fail('should never get here');
    }
}
