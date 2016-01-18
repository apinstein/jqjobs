#!/bin/bash
#
# This script is a test rig for concurrent insertion of jobs and concurrent worker execution.
# It also helps test the locking to be sure the queue is uninterrupted by pg_dump (backup).
# To play with different lock modes, see JQStore_Propel::next()
#
# POSTGRES TUNING: (samples below are tested on OS X)
#
# Typical installs of PG don't have enough connections to do high concurrency. Try editing postgresql.conf:
# max_connections = 200
#
# Then restart postgres (port unload postgresql91-server; port load postgresql91-server)
#
# You will likely need to bump shared memory for that to work
# sysctl -w kern.sysv.shmmax=16777216 kern.sysv.shmall=8192
# sysctl -h kern.sysv

# debugging aids
# set -e # stop on error -- if your script stops unceremoniously, don't forget this is here!
# set -x # print each statement before running

## DB CONFIG
db=jqjobs_test
dbuser=postgres
dbhost=localhost
dbport=5432
dbpass=
# if you change any DB params be sure to switch the dsn in ../../lib/propel/jqjobs-conf.php

# TEST CONFIG
# How many concurrent workers should be run for enqueuing/running jobs?
# NOTE: pg's default num connections is around 20; might need to bump to get this to work at higher numbers
concurrency=90
# How many jobs should we run per "thread"?
jobs_per_worker=30
### END CONFIG

## INTERNALS ##
logfile=/tmp/jqjobs-concurrency.log
queuecount=$(expr $jobs_per_worker \* $concurrency)
tmp_backup=___temp_backup__
PSQL_BIN=${PSQL_BIN:-/opt/local/lib/postgresql93/bin/psql}
PG_DUMP_BIN=${PSQL_BIN:-/opt/local/lib/postgresql93/bin/pg_dump}
SEQ_BIN=${SEQ_BIN:-/opt/local/libexec/gnubin/seq}
PHP_BIN=${PHP_BIN:-php54}

export db dbuser dbhost dbport dbpass
if [ -n ${dbpass} ]; then
    export PGPASSWORD=${dbpass}
    export dbpassdsn="password={$dbpass}"
else
    unset PGPASSWORD
    export dbpassdsn=""
fi

echo "Provisioning test database"
${PSQL_BIN} -q -U $dbuser -h ${dbhost} -p ${dbport} -d postgres -c "SELECT pg_terminate_backend(pg_stat_activity.procpid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '${db}' AND procpid <> pg_backend_pid();"
${PSQL_BIN} -q -U $dbuser -h ${dbhost} -p ${dbport} -d postgres -c "drop database if exists ${db};"
${PSQL_BIN} -q -U $dbuser -h ${dbhost} -p ${dbport} -d postgres -c "create database ${db};"
${PSQL_BIN} -q -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "drop table if exists jqstore_managed_job;"
${PSQL_BIN} -q -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "drop table if exists mp_version;"

${PSQL_BIN} -q -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "\\l" | grep ${db}
if [ $? -ne 0 ]; then
    echo "Couldn't provision test DB."
    exit 1
fi


echo "Creating tables..."
pushd ../..
$PHP_BIN ./vendor/bin/mp -f -V 0
$PHP_BIN ./vendor/bin/mp -f -x "pgsql:dbname=${db};user=${dbuser};${dbpassdsn};host=${dbhost};port=${dbport}" -m head
popd

echo
echo "Starting test... params:"
echo "Number of threads:    ${concurrency}"
echo "Jobs per thread:      ${jobs_per_worker}"
echo "Total jobs:           ${queuecount}"
echo

echo "Starting pg_dump process in background..."
${PG_DUMP_BIN} -U ${dbuser} -h ${dbhost} -p ${dbport} ${db} | gzip -9 > $tmp_backup && echo "*********** pg_dump FINISHED ***********" &
pg_dump_pid=$!

#####
## For the concurrency test, we will generate $concurrency threads of $jobs_per_worker

## Use a tempfile to collate al job runs to ensure that $queuecount jobs were actually created AND processed.
cat /dev/null > $logfile

echo "Enqueueing $queuecount jobs... if this is not printing output then it's blocked against pg_dump"
time ${SEQ_BIN} ${jobs_per_worker} | xargs -n 1 -P $concurrency -I {} sh -c "${PHP_BIN} jq-test-enqueue.php {} ${concurrency} ${logfile} || true" && echo "Enqueueing done!" &
enqueuePID=$!

echo "Starting $concurrency workers to process jobs. If you don't see output before the pg_dump finishes, then it means the workers are blocked against pg_dump"
time ${SEQ_BIN} ${concurrency} | xargs -n 1 -P $concurrency sh -c "${PHP_BIN} jq-test-worker.php ${jobs_per_worker} || true" && echo "Job processing done!" &
workerPID=$!
pstree $workerPID
echo WORKERPS $workerPID

wait $enqueuePID $workerPID
jobsSuccessfullyRun=`wc -l /tmp/jqjobs-concurrency.log | cut -f 1 -d ' '`
if [[ $jobsSuccessfullyRun -ne $queuecount ]]; then
    echo "Only ${jobsSuccessfullyRun} of ${queuecount} successfully processed. Something probably went wrong."
    exit 2
fi
echo
echo "All concurrency jobs finished..."
echo

echo "Displaying leftover jobs (should be 0 if concurrency worked correctly)"
${PSQL_BIN} -t -U $dbuser -h ${dbhost} -p ${dbport} -d ${db} -c "select count(*) as unprocessed_job_count from jqstore_managed_job;"

kill $pg_dump_pid > /dev/null 2>&1 && echo "Killed still-running pg_dump" || echo "pg_dump already finished"
rm $tmp_backup

echo
echo "Test complete."
