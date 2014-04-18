#!/bin/bash
#
# This script is a test rig for concurrent insertion of jobs and concurrent worker execution.
# It also helps test the locking to be sure the queue is uninterrupted by pg_dump (backup).
# To play with different lock modes, see JQStore_Propel::next()

set -e # stop on error
#set -x # print each statement before running

## DB CONFIG
db=jqjobs_test
dbuser=postgres
dbhost=localhost
dbport=5432
dbpass=
# if you change any DB params be sure to switch the dsn in ../../lib/propel/jqjobs-conf.php

# TEST PARAMS
queuecount=10000
concurrency=10
jobs_per_worker=20

## INTERNALS ##
tmp_backup=___temp_backup__
pg_bin_dir=/opt/local/lib/postgresql93/bin
seq_bin=/opt/local/libexec/gnubin/seq
php_bin=php54

export db dbuser dbhost dbport dbpass
if [ -n ${dbpass} ]; then
    export PGPASSWORD=${dbpass}
    export dbpassdsn="password={$dbpass}"
else
    unset PGPASSWORD
    export dbpassdsn=""
fi

echo "Provisioning test database"
${pg_bin_dir}/psql -q -U $dbuser -h ${dbhost} -p ${dbport} -d postgres -c "drop database if exists ${db};"
${pg_bin_dir}/psql -q -U $dbuser -h ${dbhost} -p ${dbport} -d postgres -c "create database ${db};"
${pg_bin_dir}/psql -q -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "drop table if exists jqstore_managed_job;"
${pg_bin_dir}/psql -q -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "drop table if exists mp_version;"

${pg_bin_dir}/psql -q -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "\\l" | grep ${db}
if [ $? -ne 0 ]; then
    echo "Couldn't provision test DB."
    exit 1
fi


echo "Creating tables..."
pushd ../..
$php_bin ./vendor/bin/mp -f -V 0
$php_bin ./vendor/bin/mp -f -x "pgsql:dbname=${db};user=${dbuser};${dbpassdsn};host=${dbhost};port=${dbport}" -m head
popd

echo "Starting pg_dump process in background..."
${pg_bin_dir}/pg_dump -U ${dbuser} -h ${dbhost} -p ${dbport} ${db} | gzip -9 > $tmp_backup && echo "*********** pg_dump FINISHED ***********" &
pg_dump_pid=$!

echo "Enqueueing $queuecount jobs... if this is not printing output then it's blocked against pg_dump"
time ${seq_bin} $(expr $queuecount / $jobs_per_worker) | xargs -n 1 -P $concurrency -I {} ${php_bin} jq-test-enqueue.php {} ${jobs_per_worker} && echo "Enqueueing done!" &
echo "Starting $concurrency workers to process jobs. If you don't see output before the pg_dump finishes, then it means the workers ae blocked against pg_dump"
time ${seq_bin} $(expr $queuecount / $jobs_per_worker) | xargs -n 1 -P $concurrency ${php_bin} jq-test-worker.php ${jobs_per_worker} && echo "Job processing done!"

echo "Displaying leftover jobs (should be 0 if concurrency worked correctly)"
${pg_bin_dir}/psql -t -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "select count(*) as unprocessed_job_count from jqstore_managed_job;"

kill $pg_dump_pid > /dev/null 2>&1 && echo "Killed still-running pg_dump" || echo "pg_dump already finished"
rm $tmp_backup
