<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class AWSTest extends PHPUnit_Framework_TestCase
{
    function testThrowHelpfulExceptionWhenAwsSdkNotLoaded()
    {
        $this->setExpectedException('AWSClientException');

        new AWSClient('us-east-1', 'Nuchamp');
    }
}
