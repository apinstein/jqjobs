<?php
// vim: set expandtab tabstop=4 shiftwidth=4:

/**
 * JQJobs is a job queue infrastructure for PHP.
 *
 * The job system has only a few parts:
 * - {@link JQJob} is an interface for a class that does actual work.
 * - {@link JQManagedJob} is a wrapper for JQJob's which contains metadata used to manage the job (status, priority, etc).
 * - {@link JQStore} is where JQManagedJob's are persisted. The application queues jobs in a JQStore for later processing.
 * - {@link JQWorker} runs jobs from the queue. It is typically run in a background process.
 *
 * The JQStore manages the queue and persistence of the JQManagedJob's. 
 *
 * JQStore is an interface, but the job system ships with several concrete implementations. The system is architected
 * in this manner to allow the job store to be migrated to different backing stores (memcache, db, Amazon SQS, etc).
 * JQStore implementations are very simple.
 *
 * Jobs that complete successfully are removed from the queue immediately. Jobs that fail are retried until maxAttempts is reached, and then they are marked FAILED and
 * left in the queue. It's up to the application to cleanup failed entries.
 *
 * @todo Jobs that are stuck running for longer than maxExecutionTime will be retried or failed as appropriate. Not sure whose responsibility this should be; server failure or job failure?
 *
 * If the application requires an audit log or archive of job history, it should implement this in run()/cleanup() for each job, or in a custom JQStore subclass.
 *
 * // The minimal amount of work needed to use a JQJobs is 1) create at least one job; 2) create a queuestore; 3) add jobs; 4) start a worker.
 * // 1) Create a job
 * class SampleJob extends JQJob
 * {
 *     function __construct($info) { $this->info = $info; }
 *     function run(JQManagedJob $mJob) { print $this->description() . "\n"; } // no-op
 *     function cleanup() { print "cleanup() {$this->description()}\n"; }
 *     function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) { print "SampleJob [Job {$mJob->getJobId()}] {$oldStatus} ==> {$mJob->getStatus()} {$message}\n"; }
 *     function description() { return "Sample job {$this->info}"; }
 *     function coalesceId() { return NULL; }
 * }
 * 
 * // 2) create a queuestore
 * $q = new JQStore_Array();
 *
 * // alternatively; create a db-based queue with Propel:
 * $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME);
 * $q = new JQStore_Propel('JQStoreManagedJob', $con);
 * 
 * // 3) Add jobs
 * foreach (range(1,10) as $i) {
 *     $q->enqueue(new SampleJob($i));
 * }
 * 
 * // 4) Start a worker to run the jobs.
 * $w = new JQWorker($q);
 * $w->start();
 *
 * @package JQJobs
 */

require_once dirname(__FILE__) . '/JQJob.php';
require_once dirname(__FILE__) . '/JQManagedJob.php';

require_once dirname(__FILE__) . '/JQStore.php';
require_once dirname(__FILE__) . '/JQStore/Autoscalable.php';
require_once dirname(__FILE__) . '/JQStore/Array.php';
require_once dirname(__FILE__) . '/JQStore/Propel.php';

require_once dirname(__FILE__) . '/JQWorker.php';

require_once dirname(__FILE__) . '/JQAutoscaler.php';
require_once dirname(__FILE__) . '/JQScalable.php';
require_once dirname(__FILE__) . '/JQScalable/Noop.php';
require_once dirname(__FILE__) . '/JQScalable/Heroku.php';
require_once dirname(__FILE__) . '/JQScalable/AWS.php';
require_once dirname(__FILE__) . '/JQScalable/ChimeraScaler.php';

