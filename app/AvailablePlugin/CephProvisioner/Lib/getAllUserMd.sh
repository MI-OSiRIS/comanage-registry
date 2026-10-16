#!/bin/bash
P='/srv/comanage/registry-current/app/tmp/cache/users-md'
SP='/usr/local/bin'
#Since we're reading the md data from the source we should clear the cache files first
#rm -f $P/cached-*-md-data.out
while read arg; do
  $SP/getUserMdCached.sh $arg &
done
wait
echo 'Done!'
