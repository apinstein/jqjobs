<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class HerokuTest extends PHPUnit_Framework_TestCase
{
    function testCountsZeroWorkersWhenHerokuIsDown()
    {
        // Mock HerokuClient
        $mock = $this->getMockBuilder('HerokuClient')
                     ->disableOriginalConstructor()
                     ->getMock();
        
        // Raise on ps
        $mock->expects($this->once())
             ->method('ps')
             ->will($this->throwException(new HerokuClient500Exception("Mock outage.")));

        $herokuScaler = new JQScalable_Heroku($mock);
        $this->assertEquals(0, $herokuScaler->countCurrentWorkersForQueue('nonexistentQueue'));
    }

    function testScalingDoesNotCrashWhenHerokuIsDown()
    {
        // Mock HerokuClient
        $mock = $this->getMockBuilder('HerokuClient')
                     ->disableOriginalConstructor()
                     ->getMock();
        
        // Raise on psScale
        $mock->expects($this->once())
             ->method('psScale')
             ->will($this->throwException(new HerokuClient500Exception("Mock outage.")));

        $herokuScaler = new JQScalable_Heroku($mock);
        $herokuScaler->setCurrentWorkersForQueue(100, 'nonexistentQueue');
    }
}
