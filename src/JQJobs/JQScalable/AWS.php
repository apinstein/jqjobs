<?php

class JQScalable_AWS implements JQScalable
{
    private $awsClient;

    function __construct($awsClient)
    {
        $this->awsClient = $awsClient;
    }

    function minSecondsToProcessDownScale()
    {
        return 0;
    }

    function countCurrentWorkersForQueue($queueName)
    {
        return $this->awsClient->countInstances();
    }

    function setCurrentWorkersForQueue($desiredWorkers, $queueName)
    {
        $maximumAllowedWorkers = $this->awsClient->maxInstances();

        // Truncate to the max so we don't ask for too many.
        $realisticDesiredWorkers = min($maximumAllowedWorkers, $desiredWorkers);

        if ($realisticDesiredWorkers < $desiredWorkers)
        {   // Log it if we truncated
            print "Needed more than max allowed workers, truncating to {$realisticDesiredWorkers}";
        }

        // If we need more workers, tell EC2
        $currentWorkers = $this->countCurrentWorkersForQueue($queueName);
        if ($realisticDesiredWorkers > $currentWorkers)
        {
            $this->awsClient->setDesiredCapacity($realisticDesiredWorkers);
        }

        // If we need less workers, do nothing.
        // Our workers die on their own if idle at the end of the billing hour.
    }
}

class AWSClientException extends Exception {}

class AWSClient
{
    private $autoScalingClient;
    private $autoScalingGroupName;

    function __construct($region, $autoScalingGroupName)
    {
        if (!class_exists('\Aws\AutoScaling\AutoScalingClient'))
        {
            throw new AWSClientException("Please install the AWS SDK in order to use the AWS autoscaling functionality of JQJobs.");
        }

        if(!getenv("AWS_ACCESS_KEY_ID") || !getenv("AWS_SECRET_ACCESS_KEY"))
        {
            throw new AWSClientException("AWS credentials not found. Please set environment variables AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY.");
        }

        $autoScalingSettings = array(
            'region' => $region
        );
        $this->autoScalingClient = \Aws\AutoScaling\AutoScalingClient::factory($autoScalingSettings);
        $this->autoScalingGroupName = $autoScalingGroupName;
    }

    function countInstances()
    {
        $description = $this->describeAutoScalingGroup();
        $instanceCount = count($description["Instances"]);

        return $instanceCount;
    }

    function maxInstances()
    {
        // First ask the AutoScaling Group for its max.
        $description = $this->describeAutoScalingGroup();
        $maxSize = $description["MaxSize"];

        return $maxSize;
    }

    function describeAutoScalingGroup()
    {
        // Ask the appropriate autoscaling group to describe itself.
        $response = $this->autoScalingClient->describeAutoScalingGroups(array(
            'AutoScalingGroupNames' => array($this->autoScalingGroupName)
        ));

        // Return the first (and only) one.
        return $response["AutoScalingGroups"][0];
    }

    function setDesiredCapacity($capacity)
    {
        $this->autoScalingClient->setDesiredCapacity(array(
            'AutoScalingGroupName' => $this->autoScalingGroupName,
            'DesiredCapacity'      => $capacity,
        ));
    }
}