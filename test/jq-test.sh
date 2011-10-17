#!/bin/bash
#
# This script is a test rig for concurrent insertion of jobs and concurrent worker execution.
# It also helps test the locking to be sure the queue is uninterrupted by pg_dump (backup).
# To play with different lock modes, see JQStore_Propel::next()

#enqueue 100 jobs
db=virtualtour_dev
dbuser=virtualtour
queuecount=100
concurrency=10
tmp_backup=___temp_backup__

echo "Truncating jqstore_managed_job"
psql84 -q -U $dbuser $db -c "truncate jqstore_managed_job;"

echo "Starting pg_dump process in background..."
/opt/local/lib/postgresql84/bin/pg_dump -U virtualtour virtualtour_dev | gzip -9 > $tmp_backup && echo "*********** pg_dump FINISHED ***********" &
pg_dump=$!

echo "Enqueueing $queuecount jobs... if this is not printing output then it's blocked against pg_dump"
/opt/local/libexec/gnubin/seq $queuecount | xargs -n 1 -P $concurrency -I {} php ./jq-test-enqueue.php {}
echo "Starting $concurrency workers to process jobs. If you don't see output before the pg_dump finishes, then it means the workers ae blocked against pg_dump"
/opt/local/libexec/gnubin/seq $queuecount | xargs -n 1 -P $concurrency php jq-test-worker.php

echo "Displaying leftover jobs (should be 0 if concurrency worked correctly)"
psql84 -t -U $dbuser $db -c "select count(*) as unprocessed_job_count from jqstore_managed_job;"

kill $pg_dump
rm $tmp_backup
