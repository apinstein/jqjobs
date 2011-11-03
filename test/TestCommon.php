<?php

require_once dirname(__FILE__) . '/../JQJobs.php';

ini_set('include_path', 
    dirname(__FILE__) . "/../externals/pear/php"
    . ":" . dirname(__FILE__) . "/../lib/propel"
);
require_once 'propel/Propel.php';
Propel::init(dirname(__FILE__) . "/../lib/propel/jqjobs-conf.php");

/************** JQStore_Propel Genterator (TEST DB) ********************/

function getTestJQStore()
{
    return new JQStore_Propel('JQStoreManagedJob', Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME));
}

/************** TEST JOBS ****************/

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
    function coalesceId() { return NULL; }
}

class QuietSimpleJob implements JQJob
{
    protected $job;
    function __construct($jobid)
    {
        $this->job=$jobid;
    }
    function run() {}
    function cleanup() {}
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "job {$this->job}"; }
    function coalesceId() { return NULL; }
}


class SampleJob implements JQJob
{
    function __construct($info) { $this->info = $info; }
    function run() { $this->info->counter++; } // no-op
    function coalesceId() { return NULL; }
    function cleanup() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Sample job"; }
}

class SampleCoalescingJob extends SampleJob
{
    function __construct($id)
    {
        $this->id = $id;
    }
    function run() {}
    function coalesceId() { return $this->id; }
    function cleanup() { }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Coalescing job {$this->id}"; }
}

class SampleFailJob implements JQJob
{
    function __construct($info) { $this->info = $info; }
    function run() { trigger_error("something went boom", E_USER_ERROR); }
    function cleanup() { }
    function coalesceId() { return NULL; }
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}
    function description() { return "Sample FAIL job"; }
}
