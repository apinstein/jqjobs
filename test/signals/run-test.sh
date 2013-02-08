#!/bin/bash

PHP_COMMAND=${PHP_COMMAND:-php}

echo "****************************************"
echo "TEST #1: SUCCESSFUL GRACEFUL SHUTDOWN..."
echo "****************************************"
SECONDS_BETWEEN_TERM_AND_KILL=6
${PHP_COMMAND} ./jq-test-signal-worker.php &
workerPID=$!
sleep 1
echo "******* SENDING SIGTERM ****************"
kill -TERM $workerPID
echo "******* WAITING FOR GRACEFUL SHUTDOWN..."
wait $workerPID

echo ""
echo ""
echo ""
echo "************************************************************"
echo "TEST #2: KILL SHUTDOWN AFTER UNSUCCESSFUL GRACEFUL PERIOD...";
echo "************************************************************"
SECONDS_BETWEEN_TERM_AND_KILL=2
${PHP_COMMAND} ./jq-test-signal-worker.php &
workerPID=$!
sleep 1
echo "****** SENDING SIGTERM: ${SECONDS_BETWEEN_TERM_AND_KILL} seconds until SIGKILL"
kill -TERM $workerPID
sleep ${SECONDS_BETWEEN_TERM_AND_KILL}
echo "****** SENDING SIGKILL..."
kill -KILL $workerPID
echo "*************** DONE ***************"
