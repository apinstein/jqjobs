#!/bin/bash

PHP_BIN=${PHP_BIN:-php}

SECONDS_TO_LET_JOB_RUN_BEFORE_TERM=1
SECONDS_BETWEEN_TERM_AND_KILL=4
# proper tuning for the workers is to configure JQJobs to forecfully kill any process 2s before the "system" would send the SIGKILL (allowing enough time to update the DB with the retry status)
(( JQJOBS_HARD_SHUTDOWN_LIMIT = ${SECONDS_BETWEEN_TERM_AND_KILL}-2 ))

echo "SECONDS_TO_LET_JOB_RUN_BEFORE_TERM: ${SECONDS_TO_LET_JOB_RUN_BEFORE_TERM}"
echo "SECONDS_BETWEEN_TERM_AND_KILL: ${SECONDS_BETWEEN_TERM_AND_KILL}"
echo "JQJOBS_HARD_SHUTDOWN_LIMIT: ${JQJOBS_HARD_SHUTDOWN_LIMIT}"
echo ""
echo ""
echo ""

echo "****************************************"
echo "TEST #1: SUCCESSFUL GRACEFUL SHUTDOWN..."
echo "****************************************"
${PHP_BIN} ./jq-test-signal-worker.php '{ "jobRunTime": 2, "systemTermToKillWindow": '${JQJOBS_HARD_SHUTDOWN_LIMIT}' }'   &
workerPID=$!
sleep ${SECONDS_TO_LET_JOB_RUN_BEFORE_TERM}
echo "******* SENDING SIGTERM ****************"
kill -TERM $workerPID
echo "******* WAITING FOR GRACEFUL SHUTDOWN..."
wait $workerPID
echo "******* EXPECT: GRACEFUL SHUTDOWN..."
echo "******* EXPECT: ONLY 1 JOB SHOULD'VE RUN... THERE IS ANOTHER JOB IN THE DB BUT THE WORKER SHOULD LEAVE THAT"

echo ""
echo ""
echo ""
echo "************************************************************"
echo "TEST #2: KILL SHUTDOWN AFTER UNSUCCESSFUL GRACEFUL PERIOD...";
echo "************************************************************"
${PHP_BIN} ./jq-test-signal-worker.php '{ "jobRunTime": 10, "systemTermToKillWindow": '${JQJOBS_HARD_SHUTDOWN_LIMIT}' }' &
workerPID=$!
sleep ${SECONDS_TO_LET_JOB_RUN_BEFORE_TERM}
echo "****** SENDING SIGTERM: ${SECONDS_BETWEEN_TERM_AND_KILL} seconds until SIGKILL"
kill -TERM $workerPID
sleep ${SECONDS_BETWEEN_TERM_AND_KILL}
echo "****** SENDING SIGKILL... EXPECTATION IS THAT JQJOBS ALREADY EXITED BEFORE OUR SYSTEM KILL TIME OF ${SECONDS_TO_LET_JOB_RUN_BEFORE_TERM}"
kill -KILL $workerPID
SIGKILL_RESULT=$?
if [[ $SIGKILL_RESULT -eq 1 ]]; then
    echo "SUCCESS: worker pid ${workerPID} successfully exited before system SIGKILL sent."
else
    echo "ERROR: Kill succeeded, but the process should've already been gone."
fi
echo "*************** DONE ***************"
