<?php

/**
 * JQStores that want to support autoscaling should also implement this interface.
 */
interface JQStore_Autoscalable
{
    function setAutoscaler(JQAutoscaler $as);
    function runAutoscaler();
}
