<?php

/**
 * A test queue worker. Processes N jobs and exists. Will also exit if "no jobs" several times.
 */
require_once dirname(__FILE__) . '/../TestCommon.php';

$exitAfterNJobs = $argv[1];

$q = getTestJQStore();
$w = new JQWorker($q, array(
    'queueName'         => 'concurrency-test',
    'verbose'           => true,
    'exitIfNoJobs'      => true,
    'exitIfNoJobsCount' => 3,                       // need enough chances to find add'l jobs if enqueing hasn't finished
    'wakeupEvery'       => 2,
    'exitAfterNJobs'    => $exitAfterNJobs
));
$w->start();
