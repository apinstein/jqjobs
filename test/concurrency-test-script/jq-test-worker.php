<?php

/**
 * A test queue worker. Processes 1 job (or 0) and immediately exits.
 */
require_once dirname(__FILE__) . '/jq-test-shared.php';
require_once dirname(__FILE__) . '/jq-test-job.php';

$q = getTestJQStore();
$w = new JQWorker($q, array('queueName' => 'concurrency-test', 'verbose' => true, 'exitIfNoJobs' => true, 'exitAfterNJobs' => 1));
$w->start();
