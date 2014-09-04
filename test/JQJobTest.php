<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQJobTest extends PHPUnit_Framework_TestCase
{
    public function testEnqueueOptionDefaults()
    {
        $testJob = new JQTestJob();
        $this->assertEquals(
            array(
                'priority'    => 0,
                'maxAttempts' => 1,
                'queueName'   => 'test'
            ),
            $testJob->getEnqueueOptions()
        );
    }

    public function testInjectingOneEnqueueOption()
    {
        $testJob = new JQTestJob();
        $testJob->setEnqueueOption('maxAttempts', 7);
        $this->assertEquals(
            array(
                'priority'    => 0,
                'maxAttempts' => 7,
                'queueName'   => 'test',
            ),
            $testJob->getEnqueueOptions()
        );

        $testJob->setEnqueueOption('maxRuntimeSeconds', 20);
        $this->assertEquals(
            array(
                'priority'          => 0,
                'maxAttempts'       => 7,
                'queueName'         => 'test',
                'maxRuntimeSeconds' => 20,
            ),
            $testJob->getEnqueueOptions()
        );
    }

    public function testInjectingManyEnqueueOptions()
    {
        $testJob = new JQTestJob();
        $testJob->setEnqueueOptions(array(
            'maxRuntimeSeconds' => 9,
            'priority'          => 5
        ));
        $this->assertEquals(
            array(
                'priority'          => 5,
                'maxAttempts'       => 1,
                'queueName'         => 'test',
                'maxRuntimeSeconds' => 9,
            ),
            $testJob->getEnqueueOptions()
        );
    }

    public function testInjectingEnqueueOptionsAtConstruction()
    {
        $testJob = new JQTestJob(array(
            'maxRuntimeSeconds' => 7,
            'priority'          => 3
        ));
        $this->assertEquals(
            array(
                'priority'          => 3,
                'maxAttempts'       => 1,
                'queueName'         => 'test',
                'maxRuntimeSeconds' => 7,
            ),
            $testJob->getEnqueueOptions()
        );
    }
}
