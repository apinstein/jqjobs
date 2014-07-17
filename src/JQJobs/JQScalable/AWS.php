<?php

class JQScalable_AWS implements JQScalable
{
    private $autoScalingClient;
    private $autoScalingGroupName;

    function __construct($region, $autoScalingGroupName)
    {
        if (!class_exists('\Aws\AutoScaling\AutoScalingClient'))
        {
            throw new Exception("Please install the AWS SDK in order to use the AWS autoscaling functionality of JQJobs.");
        }

        if(!getenv("AWS_ACCESS_KEY_ID") || !getenv("AWS_SECRET_ACCESS_KEY"))
        {
            throw new Exception("AWS credentials not found. Please set environment variables AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY.");
        }
        
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
        return count($this->describeAutoScalingGroup()["Instances"]);
    }

    function setCurrentWorkersForQueue($numWorkers, $queueName)
    {
        // First ask the AutoScaling Group for its max.
        $maximumAllowedWorkers = $this->describeAutoScalingGroup()["MaxSize"];
        // Truncate to the max so we don't ask for too many.
        $realisticWorkers = min($maximumAllowedWorkers, $numWorkers);
        // Log it if we truncated
        if ($realisticWorkers < $numWorkers)
        {
            print "Needed more than max allowed workers, truncating to {$realisticWorkers}";
        }

        // If we need more workers, tell EC2
        if ($realisticWorkers > $this->countCurrentWorkersForQueue($queueName))
        {
            $this->setDesiredCapacity($realisticWorkers);
        }
        // If we need less workers, do nothing
    }

    private function describeAutoScalingGroup()
    {
        // Ask the appropriate autoscaling group to describe itself.
        $this->autoScalingClient->describeAutoScalingGroups(array(
            'AutoScalingGroupNames' => array($this->autoScalingGroupName)
        ));

        // Return the first (and only) one.
        return $response["AutoScalingGroups"][0];
    }

    private function setDesiredCapacity($capacity)
    {
        $this->autoScalingClient->setDesiredCapacity(array(
            'AutoScalingGroupName' => $this->autoScalingGroupName,
            'DesiredCapacity'      => $capacity,
        ));
    }
}
