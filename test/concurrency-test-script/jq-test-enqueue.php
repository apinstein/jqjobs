<?php

// jq-test-enqueue.php prefix numToEnqueue
// Enqueues numToEnqueue jobs with the passed prefix

require_once dirname(__FILE__) . '/../TestCommon.php';

if ($argc !== 3 and $argc !== 4) throw new Exception("Usage: jq-test-enqueue.php prefix numToEnqueue [logfile]");

$prefix = trim($argv[1]);
$numToEnqueue = $argv[2];
$logfile = isset($argv[3]) ? $argv[3] : NULL;

$q = getTestJQStore();
for ($jobSeq = 1; $jobSeq <= $numToEnqueue; $jobSeq++) {
    $cJobId = "{$prefix}-#{$jobSeq}";
    $q->enqueue(new ConcurrencyTestJob($cJobId, $logfile));
    print "Enqueued job {$cJobId}\n";
}

