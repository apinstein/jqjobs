JQJobs is a job queue infrastructure for PHP.

Features

* Very light-weight and easy-to-use.
* Supports multiple job types.
* Supports multiple queues & binding workers to specific queues.
* Supports jobs that proxy the work to third-party services. This allows other jobs to be worked on but tracks the status of the third-party job through JQJobs.
* Tracks job enqueue time, start time, and finish time.
* Priority scheduling.
* Coalescing job support (if a job with the same coalesceId is enqueued, no duplicate job is created; the original is returned). This is basically a lightweight built-in mutex to help you prevent from creating duplicate jobs for the same "thing".
* Tested in highly-concurrent production environment with over 3M jobs processed over 2+ years.
* Queue store is architecturally independent of JQJobs; use our JQStore_Array or JQStore_Propel (db) or write your own.
* Workers automatically pre-flight memory availability and gracefully restart in low-memory situations to avoid OOM's during job processing.
* Workers automatically check all source code files in use and gracefully restart if any underlying code has been modified.
* Logs failed job messages.
* Workers designed to be run under runit or similar process supervisor for maintenance-free operation.
* Good test coverage.

Roadmap

* Auto-retry failed jobs
* Queue admin tool (cli & gui)

The job system has only a few parts:

* JQJob is an interface for a class that does actual work.
* JQManagedJob is a wrapper for JQJob's which contains metadata used to manage the job (status, priority, etc).
* JQStore is where JQManagedJob's are persisted. The application queues jobs in a JQStore for later processing.
* JQWorker runs jobs from the queue. It is typically run in a background process.

The JQStore manages the queue and persistence of the JQManagedJob's.

JQStore is an interface, but the job system ships with several concrete implementations. The system is architected
in this manner to allow the job store to be migrated to different backing stores (memcache, db, Amazon SQS, etc).
JQStore implementations are very simple.

Jobs that complete successfully are removed from the queue immediately. Jobs that fail are retried until maxAttempts is reached, and then they are marked FAILED and
left in the queue. It's up to the application to cleanup failed entries.

If the application requires an audit log or archive of job history, it should implement this in run()/cleanup() for each job, or in a custom JQStore subclass.

The minimal amount of work needed to use a JQJobs is 1) create at least one job; 2) create a queuestore; 3) add jobs; 4) start a worker.

1) Create a job

    class SampleJob implements JQJob
    {
        function __construct($info) { $this->info = $info; }
        function run() { print $this->description() . "\n"; } // no-op
        function cleanup() { print "cleanup() {$this->description()}\n"; }
        function statusDidChange(JQManagedJob $mJob, $oldStatus, $message) { print "SampleJob [Job {$mJob->getJobId()}] {$oldStatus} ==> {$mJob->getStatus()} {$message}\n"; }
        function description() { return "Sample job {$this->info}"; }
    }

2) Create a queuestore

	$q = new JQStore_Array();

    // alternatively; create a db-based queue with Propel:
    $con = Propel::getConnection(JQStoreManagedJobPeer::DATABASE_NAME);
    $q = new JQStore_Propel('JQStoreManagedJob', $con);

3) Add jobs

    foreach (range(1,10) as $i) {
        $q->enqueue(new SampleJob($i));
    }

4) Start a worker to run the jobs.

    $w = new JQWorker($q);
    $w->start();

INSTALLATION

pear install apinstein.pearfarm.org/jqjobs

See http://apinstein.pearfarm.org/apinstein/jqjobs

SOURCE

https://github.com/apinstein/jqjobs
