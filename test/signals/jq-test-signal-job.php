<?php

/**
 * A test queue worker. Processes 1 job (or 0) and immediately exits.
 */
require_once dirname(__FILE__) . '/../TestCommon.php';
class SleepJob implements JQJob
{
    public function run(JQManagedJob $mJob)
    {
        // takes ~2 seconds and isn't interrupted by SIGNALS (sleep exists immediately)
        for ($i = 0; $i < 300000; $i++) {
            if ($i % 10000 === 0) print round(100*$i/300000) . "%\n";
            crypt('fooooooooooooooobaaaaaaaaaaaaaaaaaaaaar', CRYPT_BLOWFISH);
        }
        print "100%";
        return JQManagedJob::STATUS_COMPLETED;
    }

    public function cleanup() {}
    public function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) {}

    public function coalesceId()
    {
        return microtime();
    }

    public function description()
    {
        return "Sleeping....";
    }

}
