<?php

/**
 * A test queue worker. Processes 1 job (or 0) and immediately exits.
 */
require_once dirname(__FILE__) . '/../TestCommon.php';

$exitAfterNJobs = $argv[1];

$q = getTestJQStore();
$w = new JQWorker($q, array('queueName' => 'concurrency-test', 'verbose' => true, 'exitIfNoJobs' => true, 'exitAfterNJobs' => $exitAfterNJobs));
$w->start();
