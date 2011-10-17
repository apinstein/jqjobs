<?php

require_once getenv('PHOCOA_PROJECT_CONF');
require_once 'test/unit/jqjobs/jq-test-job.php';

if ($argc !== 2) throw new Exception("Pass jobId as only argument. A positive integer.");
$jobId = (int) $argv[1];
if ($jobId <= 0) throw new Exception("Pass jobId as only argument. A positive integer.");

$queueService = VirtualTourApp::getJQStore();
$queueService->enqueue(new CTestJob($jobId), array('queueName' => 'concurrency-test'));
print "Enqueued job {$jobId}\n";

