<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class AWSTest extends PHPUnit_Framework_TestCase
{
    function testThrowHelpfulExceptionWhenAwsSdkNotLoaded()
    {
        $this->setExpectedException(
          'Exception', "Please install the AWS SDK in order to use the AWS autoscaling functionality of JQJobs."
        );

        new JQScalable_AWS('us-east-1', 'Nuchamp');
    }
}
