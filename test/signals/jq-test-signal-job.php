<?php

/**
 * A test queue worker. Processes 1 job (or 0) and immediately exits.
 */
require_once dirname(__FILE__) . '/../TestCommon.php';
class UninterruptibleJob extends JQJob
{
    protected $runForNSeconds = 3;

    public function __construct($runForNSeconds = 3)
    {
        $this->runForNSeconds = $runForNSeconds;
    }

    public function run(JQManagedJob $mJob)
    {
        // takes $runForNSeconds seconds and isn't interrupted by SIGNALS (sleep exists immediately)
        $t0 = microtime(true);
        $percentDone = 0;
        $elapsedTime = 0;
        while ($elapsedTime < $this->runForNSeconds) {
            $elapsedTime = microtime(true) - $t0;
            $percentDoneNow = (int) (100 * ($elapsedTime / $this->runForNSeconds));
            crypt('fooooooooooooooobaaaaaaaaaaaaaaaaaaaaar', CRYPT_BLOWFISH);
            if ($percentDoneNow != $percentDone)
            {
                $percentDone = $percentDoneNow;
                print "{$percentDone}%\n";
            }
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
        return "Doing uninterruptible work for {$this->runForNSeconds} seconds....";
    }

}
