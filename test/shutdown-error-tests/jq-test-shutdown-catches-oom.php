<?php

/**
 * This script verifies that a job that generates a CATCHABLE FATAL ERROR will gracefully fail the job...
 */

require_once dirname(__FILE__) . '/../TestCommon.php';

print <<<EXPLAIN
***********************************************************************************************************
  This test makes sure that an oom during job execution will result in the job being marked failed.
  This exercises our shutdown error detection.

  This test works if you see output indicating:
    - a job starts running
    - then an oom
    - finally "Status change: running => failed"
***********************************************************************************************************


EXPLAIN;

class SampleFatalJob extends SampleLoggingJob
{
    function run(JQManagedJob $mJob)
    {
        // causes a FATAL error
        ini_set('memory_limit', '10M');
        str_repeat('1234567890', 10000000);
        return JQManagedJob::STATUS_COMPLETED;
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

