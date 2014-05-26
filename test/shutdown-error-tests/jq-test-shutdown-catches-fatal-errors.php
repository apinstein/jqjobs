<?php

/**
 * This script verifies that a job that generates a CATCHABLE FATAL ERROR will gracefully fail the job...
 */

require_once dirname(__FILE__) . '/../TestCommon.php';

print <<<EXPLAIN
***********************************************************************************************************
  This test makes sure that a fatal error during job execution will result in the job being marked failed.
  This exercises our shutdown error detection.

  This test works if you see output indicating:
    - a job starts running
    - then a fatal error
    - finally "Status change: running => failed"
***********************************************************************************************************


EXPLAIN;

class SampleFatalJob extends SampleLoggingJob
{
    function run(JQManagedJob $mJob)
    {
        // causes a FATAL error
        $foo->bar();
    }
}

// run a job that will FATAL
$q = new JQStore_Array();
$goodJob = $q->enqueue(new SampleFatalJob());
if ($q->count('test') !== 1) throw new Exception("assert failed");
SampleJobCounter::reset();

// Start a worker to run the job.
$w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'verbose' => true, 'enableJitter' => false));
$w->start();

