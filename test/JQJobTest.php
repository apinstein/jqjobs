<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQTestJob extends JQJob
{
    function run(JQManagedJob $mJob) { }
    function cleanup() { }
    function coalesceId() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) { }
    function description() { }
}

class JobWithOptions extends JQTestJob
{
    function enqueueOptions()
    {
        return array_merge(
            parent::enqueueOptions(),
            array(
                'priority'    => -1,
                'queueName'   => 'test',
            )
        );
    }
}

class JQJobTest extends PHPUnit_Framework_TestCase
{
    public function testEnqueueOptionDefaults()
    {
        $testJob = new JQTestJob();
        $this->assertEquals(
            array('priority' => 0, 'maxAttempts' => 1),
            $testJob->enqueueOptions()
        );
    }

    public function testOverrideEnqueuOptions()
    {
        $testJob = new JobWithOptions();
        $this->assertEquals(
            array('priority' => -1, 'maxAttempts' => 1, 'queueName' => 'test'),
            $testJob->enqueueOptions()
        );
    }
}
