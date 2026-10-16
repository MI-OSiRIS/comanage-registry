#!/bin/bash
P='/srv/comanage/registry-current/app/tmp/cache/users-md'
#if the modification time is > 3 mins, we consider it too old. This is just for testing so the value can later be adjusted as we see fit.
if  test `find "$P/cached-$1-md-data.out" -mmin -3` && [ -s $P/cached-$1-md-data.out ]
then
    cat $P/cached-$1-md-data.out
    exit 0
fi
#rm -f $P/cached*.out
exit -1
