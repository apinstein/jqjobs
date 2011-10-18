<?php

require_once dirname(__FILE__) . '/jq-test-job.php';
require_once dirname(__FILE__) . '/../JQJobs.php';

if ($argc !== 2) throw new Exception("Pass jobId as only argument. A positive integer.");
$jobId = (int) $argv[1];
if ($jobId <= 0) throw new Exception("Pass jobId as only argument. A positive integer.");

// @todo Create a JQStore_Postgres concrete subclass and get this test working outside of a host propel app.
// NOTE: This test was run successfully before the project was factored out.
$queueService = new JQStore_Postgres('JQStoreManagedJob', $dsn);
$queueService->enqueue(new CTestJob($jobId), array('queueName' => 'concurrency-test'));
print "Enqueued job {$jobId}\n";

