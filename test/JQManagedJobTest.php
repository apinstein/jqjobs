<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQManagedJobTest extends PHPUnit_Framework_TestCase
{
    function testExceptionWhenAskedToManageASecondJob()
    {
        $this->setExpectedException('JQManagedJob_AlreadyHasAJobException');

        $mJob = new JQManagedJob(NULL, new JQTestJob()); // inject a job
        $mJob->setJob(new JQTestJob()); // then set another job (boom!)
    }

    /**
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
            'Resolving to COMPLETE   completes the job' => array(JQManagedJob::STATUS_COMPLETED,    JQManagedJob::STATUS_COMPLETED),
            'Resolving to FAILED     fails the job'     => array(JQManagedJob::STATUS_FAILED,       JQManagedJob::STATUS_FAILED),
            'Resolving to WAIT_ASYNC waits the job'     => array(JQManagedJob::STATUS_WAIT_ASYNC,   JQManagedJob::STATUS_WAIT_ASYNC),
        );
    }

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

    function testResolveAsyncJobDoesNotExistThrowsException()
    {
        // create a queuestore
        $q = new JQStore_Array();

        $this->setExpectedException('JQStore_JobNotFoundException');
        JQManagedJob::resolveWaitAsyncJob($q, 9999, array());
    }

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
