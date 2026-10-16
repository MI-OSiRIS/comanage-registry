#!/bin/bash
#sleep $[(RANDOM % 3)] && 
#sleep 1 && echo $1 >> data.out 
if [ -z "$1" ]
  then
    echo "No argument supplied. Please provide a userid as an argument." && exit 1
fi
P='/srv/comanage/registry-current/app/tmp/cache/users-md'
#/usr/bin/radosgw-admin --id=comanage --cluster=ceph  metadata get user:$1 > $P/cached-$1-md-data.out
#echo ',' >>data.out
