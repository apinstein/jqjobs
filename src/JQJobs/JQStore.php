<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * The JQStore interface.
 *
 * The JQJob system uses an interface for services that can implement the backing store for the queued jobs.
 *
 * This allows queue clients to easily switch between different backing stores without having to re-do any work.
 * Depending on the needs of your system you can use a simple queuestore (easy to set up but slower)
 * or a more complex one (harder to set up but supports higher throughput, concurrent access, etc).
 */
interface JQStore
{

    /**
     * Add a JQJob to the queue.
     *
     * Will create a new JQManagedJob to manage the job and add it to the queue.
     *
     * @param object JQJob
     * @return object JQManagedJob
     * @throws object Exception
     */
    function enqueue(JQJob $job);

    /**
     * Get the next job to run.
     *
     * NOTE: Implementers should make sure that next() has a mutex to be sure that no two workers end up running the same job twice (at the same time).
     * NOTE: Implementers should make sure to support the dynamism of the $queueName filter.
     *
     * @see JQManagedJob::isAnyQueue()
     * @see JQManagedJob::matchesQueueNameFilter()
     *
     * @param mixed Queue name(s), default NULL (any)
     *              string NULL, JQStore::QUEUE_ANY, or a specific queue name
     *              array  Array of strings of names of specific queues to perform work on.
     * @return object JQManagedJob
     * @throws object Exception
     */
    function next($queueName = null);

    /**
     * See if there is already a job for the given coalesceId in the queue.
     *
     * Don't forget that in the present implementation, successful jobs are deleted (and thus the same job can run again once successfully completed)
     * but that failed jobs are kept, meaning that you cannot re-run the job until the failed job is deleted manually.
     *
     * Notes for implementer: A NULL coalesceId should always return NULL.
     * Notes for implementer: Your enqueue() function should call this to find existing jobs. Think about the concurrency consequences of this.
     *
     * @param string The coalesceId to check for.
     * @return object JQManagedJob
     */
    function existsJobForCoalesceId($coalesceId);

    /**
     * Count the jobs in the given queue with the given status (any).
     *
     * @param string Queue name (NULL = default queue)
     * @param string Status (NULL = any status)
     * @return int
     */
    function count($queueName = null, $status = NULL);

    /**
     * Get all jobs in the JQStore matching the given queue/status.
     *
     * @param string Queue name (NULL = default queue)
     * @param string Status (NULL = any status)
     * @return array An array of JQJobs.
     */
    function jobs($queueName = NULL, $status = NULL);

    /**
     * Get a JQManagedJob from the JQStore by ID.
     *
     * @param string JobId.
     * @return object JQStoreManagedJob, or NULL if not found.
     * @throws object JQStore_JobNotFoundException
     */
    function get($jobId);

    /**
     * Get a JQManagedJob from the JQStore by ID, but LOCK the job so that it cannot be concurrently updated.
     *
     * NOTE: Some backends may not support per-job mutexes; in that case they may not allow you to lock more than one job at a time in the same process.
     *
     * @param string JobId.
     * @return object JQManagedJob If there is no exception thrown; the returned object will always be valid.
     * @throws object JQStore_JobIsLockedException if already locked
     * @throws object JQStore_JobNotFoundException if job doesn't exist (and NO LOCKS will be left open)
     * @todo Refactor so that getWithMutex() returns a LOCK object (such that locking isn't just a side effect) or refactor to "doWhileLocked"
     */
    function getWithMutex($jobId);

    /**
     * Clear the mutex on the job.
     */
    function clearMutex($jobId);

    /**
     * Get a JQManagedJob from the JQStore by the
     * coalesceId.
     *
     * @param string The coalesceId to check for.
     * @return object JQManagedJob
     * @throws object Exception
     */
    function getByCoalesceId($coalesceId);

    /**
     * Save the job (which presumably has been updated) to the backing store.
     *
     * @param object JQManagedJob
     */
    function save(JQManagedJob $job);

    /**
     * Delete the job from the backing store.
     *
     * @param object JQManagedJob
     */
    function delete(JQManagedJob $job);

    /**
     * JQStore's can use this delegate method to report failures, or archive, them, or whatver they want.
     *
     * If your application needs an audit log, you can subclass your JQStore and implement this method to easily add JQJobs-wide audit logging.
     *
     * @param object JQManagedJob The JQManagedJob for this job.
     * @param string The prior status (see JQManagedJob::STATUS_*).
     * @param string The message accompanying the status change.
     * @see JQJob::statusDidChange()
     */
    function statusDidChange(JQManagedJob $mJob, $oldStatus, $message);

    /**
     * This function will be called when a job is interrupted by a signal. The JQStore should abort any un-commited transactions to ensure that the core cleanup algorithm can perform properly.
     */
    function abort();

    /**
     * This function should iterate all running jobs, retrying (with mulligan) any jobs that are isPastMaxRuntimeSeconds()
     */
    function detectHungJobs();
}

class JQStore_JobIsLockedException extends Exception {}
class JQStore_JobNotFoundException extends Exception {}
