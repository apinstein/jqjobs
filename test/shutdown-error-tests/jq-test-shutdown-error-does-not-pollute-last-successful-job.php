<?php

/**
 * This script verifies that a job that generates a CATCHABLE FATAL ERROR will gracefully fail the job...
 */

require_once dirname(__FILE__) . '/../TestCommon.php';

print <<<EXPLAIN
***********************************************************************************************************
  This test makes sure that a fatal error AFTER a job runs successfully doesn't try to fail the
  already completed job.

  This test works if you see output indicating:
    - a successful job run
    - then a fatal error
    - no subsequent job status changes
***********************************************************************************************************


EXPLAIN;
// run a job that will FATAL
$q = new JQStore_Array();
$goodJob = $q->enqueue(new SampleLoggingJob(), array('queueName' => 'test'));
if ($q->count('test') !== 1) throw new Exception("assert failed");
SampleJobCounter::reset();

// Start a worker to run the job.
$w = new JQWorker($q, array('queueName' => 'test', 'exitIfNoJobs' => true, 'silent' => true, 'enableJitter' => false));
$w->start();

// trigger a fatal error; shouldn't fail our job, though as we should be out-of-scope.
$foo->bar();
