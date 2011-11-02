<?php

require_once dirname(__FILE__) . '/jq-test-shared.php';
require_once dirname(__FILE__) . '/jq-test-job.php';

if ($argc !== 2) throw new Exception("Pass jobId as only argument. A positive integer.");
$jobId = (int) $argv[1];
if ($jobId <= 0) throw new Exception("Pass jobId as only argument. A positive integer.");

$q = getTestJQStore();
$q->enqueue(new CTestJob($jobId), array('queueName' => 'concurrency-test'));
print "Enqueued job {$jobId}\n";

