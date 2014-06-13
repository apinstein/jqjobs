<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class AWSTest extends PHPUnit_Framework_TestCase
{
    function testItWorks()
    {
        new JQScalable_AWS('us-east-1', 'Nuchamp');
    }
}
