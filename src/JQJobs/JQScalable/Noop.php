<?php

/**
 * For testing
 */
class JQScalable_Noop implements JQScalable
{
    protected $scale = array();

    function minSecondsToProcessDownScale()
    {
        return 0;
    }
    function countCurrentWorkersForQueue($queueName)
    {
        if (!isset($this->scale[$queueName])) return 0;

        return $this->scale[$queueName];
    }
    function setCurrentWorkersForQueue($numWorkers, $queueName)
    {
        if ($numWorkers == $this->countCurrentWorkersForQueue($queueName)) return;

        $this->scale[$queueName] = $numWorkers;

        if ($numWorkers > 0)
        {
        #    declare(ticks=1);
        #    $w = new JQWorker(JobsApp::getJQStore(), array('verbose' => true, 'exitIfNoJobs' => true));
        #    $w->start();
        }
    }
}
