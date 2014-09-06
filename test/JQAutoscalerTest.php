<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQAutoscalerTest extends PHPUnit_Framework_TestCase
{
    function testSettingScalerPollingInterval()
    {
        $autoscaler = new JQAutoscaler($this->getMock('JQStore_Array'), $this->getMock('JQScalable_Noop'), array());
        $autoscaler->setScalerPollingInterval(10);
    }
}
