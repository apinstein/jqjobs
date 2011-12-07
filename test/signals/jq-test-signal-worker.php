<?php

// we have the job in a separate file as a proof-of-concept that the declare(ticks=1) works in this situation.
require_once 'jq-test-signal-job.php';

// to have JQJobs be able to gracefully handle SIGKILL (or other *immediate termination* signals) the declare() must be at this level.
declare(ticks = 1);

$q = new JQStore_Array();
$q->enqueue(new SleepJob);
$q->enqueue(new SleepJob);
$w = new JQWorker($q, array('verbose' => true, 'exitIfNoJobs' => true));
$w->start();
