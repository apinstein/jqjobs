<?php

require_once dirname(__FILE__) . '/../JQJobs.php';

class CTestJob implements JQJob
{
    protected $job;
    function __construct($jobid)
    {
        $this->job=$jobid;
    }
    function run() { print "running job {$this->job}"; }
    function cleanup() {}
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "job {$this->job}"; }
}

