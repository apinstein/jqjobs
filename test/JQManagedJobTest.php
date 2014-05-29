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
}
