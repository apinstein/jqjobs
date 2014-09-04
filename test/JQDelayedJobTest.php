<?php

require_once dirname(__FILE__) . '/TestCommon.php';

class JQDelayedJobTest extends PHPUnit_Framework_TestCase
{
    function testRequiringDelayedJob()
    {
        // Because this file is an optional requirement
        require_once dirname(__FILE__) . '/../src/JQJobs/JQDelayedJob.php';
    }
}
