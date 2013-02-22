<?php

/***
 * Infrastructure that can be scaled to manage large numbers of concurrent workers can use JQScalable to do so.
 *
 * We include a Heroku driver.
 */
interface JQScalable
{
    const WORKER_AUTOSCALER = 'autoscaler';

    function countCurrentWorkersForQueue($queue);
    function setCurrentWorkersForQueue($numWorkers, $queue);
    function minSecondsToProcessDownScale();
}
