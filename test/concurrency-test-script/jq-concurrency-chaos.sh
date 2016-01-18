#!/bin/zsh

repeat 100; do
 chaoskillpid=`ps -a -o pid,comm | grep update | sort -t ' ' -nk2 | tail -1 | cut -f 1 -d ' '`
 echo KILL $chaoskillpid
 kill -9 $chaoskillpid
 sleep 1
done
