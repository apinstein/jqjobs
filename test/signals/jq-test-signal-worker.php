<?php

// we have the job in a separate file as a proof-of-concept that the declare(ticks=1) works in this situation.
require_once 'jq-test-signal-job.php';

// to have JQJobs be able to gracefully handle SIGKILL (or other *immediate termination* signals) the declare() must be at this level.
declare(ticks = 1);

// The idea of these signal tests is to test the time between the SIGTERM and the SIGKILL; 
// THERE are 2 cases to test:
// 1. Job finishes in time window between SIGTERM and SIGKILL, expect JOB COMPLETION and GRACEFUL SHUTDOWN
//    The way this test works is to run a job that takes 3 seconds; send TERM after 1s and KILL after 7
// 2. Job doesn't finish before SIGKILL; expect JOB FAILURE (retry) AND NON-GRACEFUL SHUTDOWN
//    The way this test works is to run a job that takes 10 seconds; send TERM after 1s and KILL after 7
if (!isset($argv[1])) die("Pass one argument with integer number of seconds the job under signal test should take.");
$params = json_decode($argv[1], true);

print "Job Runtime: {$params['jobRunTime']}\n";
print "System TERM-to-KILL window: {$params['systemTermToKillWindow']}\n";

$q = new JQStore_Array();
$q->enqueue(new UninterruptibleJob($params['jobRunTime']));
$q->enqueue(new UninterruptibleJob);  // this 2nd job is there so that in test #1, that the worker has more work to do but gracefully exits anyway due to receiving the signal
$w = new JQWorker($q, array('verbose' => true, 'enableJitter' => false, 'gracefulShutdownTimeout' => $params['systemTermToKillWindow']));
$w->start();
