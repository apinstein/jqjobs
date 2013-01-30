#!/bin/bash
#
# This script is a test rig for concurrent insertion of jobs and concurrent worker execution.
# It also helps test the locking to be sure the queue is uninterrupted by pg_dump (backup).
# To play with different lock modes, see JQStore_Propel::next()

#enqueue 100 jobs
db=jqjobs_test
dbuser=postgres
queuecount=1000
concurrency=50
jobs_per_worker=20
tmp_backup=___temp_backup__
pg_bin_dir=/opt/local/lib/postgresql92/bin
seq_bin=/opt/local/libexec/gnubin/seq
php_bin=php53

echo "Provisioning test database"
${pg_bin_dir}/psql -q -U $dbuser postgres -c "drop database if exists ${db};"
${pg_bin_dir}/psql -q -U $dbuser postgres -c "create database ${db};"
if [ $? -ne 0 ]; then
    echo "Couldn't provision test DB."
    exit 1
fi

echo "Creating tables..."
pushd ../..
comice exec mp -f -V 0
comice exec mp -f -x "pgsql:dbname=${db};user=${dbuser};host=localhost" -m head
popd

echo "Starting pg_dump process in background..."
${pg_bin_dir}/pg_dump -U ${dbuser} ${db} | gzip -9 > $tmp_backup && echo "*********** pg_dump FINISHED ***********" &
pg_dump_pid=$!

echo "Enqueueing $queuecount jobs... if this is not printing output then it's blocked against pg_dump"
time ${seq_bin} $(expr $queuecount / $jobs_per_worker) | xargs -n 1 -P $concurrency -I {} ${php_bin} jq-test-enqueue.php {} ${jobs_per_worker}
echo "Starting $concurrency workers to process jobs. If you don't see output before the pg_dump finishes, then it means the workers ae blocked against pg_dump"
time ${seq_bin} $(expr $queuecount / $jobs_per_worker) | xargs -n 1 -P $concurrency ${php_bin} jq-test-worker.php ${jobs_per_worker}

echo "Displaying leftover jobs (should be 0 if concurrency worked correctly)"
${pg_bin_dir}/psql -t -U $dbuser $db -c "select count(*) as unprocessed_job_count from jqstore_managed_job;"

kill $pg_dump_pid > /dev/null 2>&1 && echo "Killed still-running pg_dump" || echo "pg_dump already finished"
rm $tmp_backup
