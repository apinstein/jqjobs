<?php

require_once dirname(__FILE__) . '/../TestCommon.php';

if ($argc !== 3) throw new Exception("Usage: jq-test-enqueue.php jobId numToEnqueue");
$jobId = (int) $argv[1];
if ($jobId <= 0) throw new Exception("Pass jobId as only argument. A positive integer.");
$numToEnqueue = $argv[2];

$q = getTestJQStore();
while ($numToEnqueue) {
    $cJobId = "{$jobId}.{$numToEnqueue}";
    $q->enqueue(new ConcurrencyTestJob($cJobId));
    $numToEnqueue--;
    print "Enqueued job {$cJobId}\n";
}

