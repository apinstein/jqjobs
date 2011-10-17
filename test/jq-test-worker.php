<?php

/**
 * A test queue worker. Processes 1 job (or 0) and immediately exits.
 */
require_once getenv('PHOCOA_PROJECT_CONF');
require_once 'test/unit/jqjobs/jq-test-job.php';

Propel::disableInstancePooling();

$q = VirtualTourApp::getJQStore();
$w = new JQWorker($q, array('queueName' => 'concurrency-test', 'verbose' => true, 'exitIfNoJobs' => true, 'exitAfterNJobs' => 1));
$w->start();
