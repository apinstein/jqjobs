<?php

/**
 *  This implements a per-queue scaling strategy. You simply initialize it
 *  with your queue names mapped to their respective scalers and start
 *  calling JQScalable methods on it. They will be routed to the
 *  appropriate JQScalable based on the queue name.
 *
 *  Example:
 *
 *  $chimera = new ChimeraScaler(array(
 *      'myQueue'         => new HerokuScaler(),
 *      'nonscalingQueue' => new NoopScaler(),
 *  ));
 *
 *  // passes through to HerokuScaler
 *  $chimera->countCurrentWorkersForQueue('myQueue');
 *
 *  // passes through to NoopScaler
 *  $chimera->setCurrentWorkersForQueue('nonscalingQueue');
**/

class ChimeraScalerBadConfigException extends Exception {}
class ChimeraScalerMissingQueueException extends Exception {}

class ChimeraScaler implements JQScalable
{
    private $queueScalerMap;

    public function __construct($config = array())
    {
        $this->ensureValidConfig($config);

        $this->queueScalerMap = $config;
    }

    private function ensureValidConfig($config)
    {
        if (empty($config))
        {
            throw new ChimeraScalerBadConfigException("No configuration provided.");
        }

        foreach($config as $queueName => $scaler)
        {
            if (!is_string($queueName)) {
                throw new ChimeraScalerBadConfigException("Bad queue name given: {$queueName}.");
            }

            if (!is_a($scaler, 'JQScalable')) {
                throw new ChimeraScalerBadConfigException("Bad Scaler argument given: {$scaler}");
            }
        }
    }

    public function getScalerByQueue($queueName)
    {
        if (isset($this->queueScalerMap[$queueName]))
        {
            $scaler = $this->queueScalerMap[$queueName];   
        }
        else
        {
            throw new ChimeraScalerMissingQueueException("Unknown queue name: {$queueName}");
        }

        return $scaler;
    }

    public function minSecondsToProcessDownscale()
    {
        $minimums = array_map(function($queueName) {
            return $queueName->minSecondsToProcessDownscale();
        }, $this->queueScalerMap);

        return max($minimums);
    }

    public function countCurrentWorkersForQueue($queue)
    {
        $scaler = $this->getScalerByQueue($queue);

        return $scaler->countCurrentWorkersForQueue($queue);
    }

    public function setCurrentWorkersForQueue($numWorkers, $queue)
    {
        $scaler = $this->getScalerByQueue($queue);

        return $scaler->setCurrentWorkersForQueue($numWorkers, $queue);
    }
}
