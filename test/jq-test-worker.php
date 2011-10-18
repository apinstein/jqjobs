<?php

/**
 * A test queue worker. Processes 1 job (or 0) and immediately exits.
 */
require_once dirname(__FILE__) . '/jq-test-job.php';
require_once dirname(__FILE__) . '/../JQJobs.php';


// @todo Create a JQStore_Postgres concrete subclass and get this test working outside of a host propel app.
// NOTE: This test was run successfully before the project was factored out.
$q = new JQStore_Postgres('JQStoreManagedJob', $dsn);
$w = new JQWorker($q, array('queueName' => 'concurrency-test', 'verbose' => true, 'exitIfNoJobs' => true, 'exitAfterNJobs' => 1));
$w->start();
