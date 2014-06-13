<?php

if (!class_exists('\Aws\AutoScaling\AutoScalingClient'))
{
    throw new Exception("Please install the AWS SDK in order to use the AWS autoscaling functionality of JQJobs.");
}

if(!getenv("AWS_ACCESS_KEY_ID") || !getenv("AWS_SECRET_ACCESS_KEY"))
{
    throw new Exception("AWS credentials not found. Please set environment variables AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY.");
}

class JQScalable_AWS implements JQScalable
{
    private $autoScalingClient;
    private $autoScalingGroupName;

    function __construct($region, $autoScalingGroupName)
    {
        $this->autoScalingGroupName = $autoScalingGroupName;

        $autoScalingSettings = array(
            'region' => $region
        );
        $this->autoScalingClient = \Aws\AutoScaling\AutoScalingClient::factory($autoScalingSettings);
    }

    function minSecondsToProcessDownScale()
    {
        return 0;
    }

    function countCurrentWorkersForQueue($queueName)
    {
        // Ask the appropriate autoscaling group
        // to describe the running instances.
        $response = $this->autoScalingClient->describeAutoScalingInstances();
        // Count them
        $numberOfInstances = count($response["AutoScalingInstances"]);

        return $numberOfInstances;
    }

    function setCurrentWorkersForQueue($numWorkers, $queueName)
    {
        // If we need more workers, tell EC2
        if ($numWorkers > $this->countCurrentWorkersForQueue($queueName))
        {
            $options = array(
                'AutoScalingGroupName' => $this->autoScalingGroupName,
                'DesiredCapacity' => $numWorkers,
            );
            $response = $this->autoScalingClient->setDesiredCapacity($options);   
        }
        // If we need less workers, do nothing
    }
}
