<?php

class DummyScalable implements JQScalable
{
    function countCurrentWorkersForQueue($queue) { return 0; }
    function setCurrentWorkersForQueue($numWorkers, $queue) { }
    function minSecondsToProcessDownScale() { return 5; }
}

class SlowDownScalable extends DummyScalable
{
    const DOWNSCALE_SECONDS = 600;

    function minSecondsToProcessDownScale() {
        return SlowDownScalable::DOWNSCALE_SECONDS;
    }
}

class ChimeraScalerTest extends PHPUnit_Framework_TestCase
{
    public $subject;
    public $mockedScaler;

    function setup()
    {
        $this->mockedScaler = $this->getMockBuilder('DummyScalable')->getMock();
        $this->subject = new ChimeraScaler(array(
            'normalQueue'      => new DummyScalable(),
            'mockQueue'        => $this->mockedScaler,
            'slowQueue'        => new SlowDownScalable(),
        ));
    }

    function testUsesGreatestMinDownscaleTime()
    {
        $shouldBe = SlowDownScalable::DOWNSCALE_SECONDS;
        $this->assertEquals($shouldBe, $this->subject->minSecondsToProcessDownScale());
    }

    function testFunctionsAreRoutedToCorrectScalers()
    {
        $functionsToMock = array(
            'countCurrentWorkersForQueue',
            'setCurrentWorkersForQueue',
        );

        // Mock the functions that should be called through the Chimera
        foreach($functionsToMock as $functionName)
        {
            $this->mockedScaler->expects($this->once())
                               ->method($functionName)
                               ->will($this->returnValue(5));
        }
        
        // Call the Chimera functions
        $this->subject->countCurrentWorkersForQueue('mockQueue');
        $this->subject->setCurrentWorkersForQueue(10, 'mockQueue');
    }

    function testThrowsWhenGivenNoConfig()
    {
        $this->setExpectedException('ChimeraScalerBadConfigException');

        new ChimeraScaler();
    }
    
    function testThrowsWhenGivenBadQueueData()
    {
        $this->setExpectedException('ChimeraScalerBadConfigException');

        new ChimeraScaler(array(
            'good' => new DummyScalable(),
            5      => new DummyScalable(),
        ));
    }

    function testThrowsWhenGivenBadScalerData()
    {
        $this->setExpectedException('ChimeraScalerBadConfigException');

        new ChimeraScaler(array(
            'good'     => new DummyScalable(),
            'alsoGood' => 'not a scaler',
        ));
    }

    function testThrowsWhenGivenAnUnconfiguredQueue()
    {
        $this->setExpectedException('ChimeraScalerMissingQueueException');

        $this->subject->countCurrentWorkersForQueue('nonExistentQueue');
    }
}
