<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * A Job interface.
 *
 * Any object that needs to perform work on an asynchronous basis can do so by implementing the JQJob interface.
 *
 * The inteface is very simple, consisting only of a run() method to perform the work, and a description() method for reporting/logging purposes.
 *
 * IMPORTANT: Objects implementing JQJob will be serialized into the JQManagedJob during persistence, so it's important that they can be safely seriazlied.
 */
abstract class JQJob
{
    function enqueueOptions()
    {
        return array(
            'priority'    => 0,
            'maxAttempts' => 1,
        );
    }

    /**
     * Run the job.
     *
     * @return string One of JQManagedJob::STATUS_WAIT_ASYNC or JQManagedJob::STATUS_COMPLETED. Throw an exception to indicate an error.
     * @throws object Exception Throw an exception if there is a problem.
     */
    abstract function run(JQManagedJob $mJob);

    /**
     * Cleanup any resources held by the job.
     *
     * This gives jobs a chance to delete any resources they may be using before the JQManagedJob is removed.
     */
    abstract function cleanup();

    /**
     * Jobs can use this delegate method to report failures, or archive, them, or whatver they want.
     *
     * If some other part of your application needs to track status changes to the JQJob, it can use this callback to do so.
     *
     * @param object JQManagedJob The JQManagedJob for this job.
     * @param string The prior status (see JQManagedJob::STATUS_*).
     * @param string The message accompanying the status change.
     * @see JQStore::statusDidChange()
     */
    abstract function statusDidChange(JQManagedJob $mJob, $oldStatus, $message);

    /**
     * A description for the job.
     *
     * This is mostly for reporting/logging purposes.
     *
     * @return string
     */
    abstract function description();

    /**
     * A unique ID for the job.
     *
     * If NULL, the job will not be subject to coalescing.
     *
     * If NOT NULL, the job will not be queued if there already exist a job for the provided coalesceId.
     *
     * The scope for coalesceId is Application. Thus an application needs to take care to prevent collisions of the coalesceId across jobs of different types.
     *
     * The recommended way to do that is to prefix the coalesceId with the class name of the job.
     *
     * @return string
     */
    abstract function coalesceId();
}
