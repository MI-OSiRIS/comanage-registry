#!/bin/bash

# This is a helper script meant to be run with sudo as a user having
# permissions to create COU dirs and set permissions, placement, etc

# Requires arguments: -c COU, -d parent directory to create COU dir (must be cephFS)

# exit codes:
# 0 success or directory exists 
# (this script does not do permission fixups in case they are intentionaly changed after provisioning)
# 2 bad option or option arg doesn't match required format
# 3 mount did not stat as type ceph
# 4 mkdir failed
# 5 setting directory ownership or permissions failed
# 6 setting fattr for data pool placement failed

while getopts ":o:c:d:" opt; do
    case ${opt} in 
        c ) 
            if [ -n "$OPTARG" ]; then
                COU=$OPTARG
            else
                echo "Error: Empty string not allowed for COU argument"
                exit 2
            fi
            ;;
        d ) 
            if [[ $OPTARG == *'/'* ]]; then
                DIR=$OPTARG
            else
                echo "Error: Parent directory does not contain '/', does not appear to be path"
                exit 2
            fi
            ;;
        : ) 
            exit 2
            ;;
        \? ) 
            exit 2
            ;;
    esac
done

COUDIR=$(echo $COU | tr "[:upper:]" "[:lower:]")
DIRPATH="${DIR}/${COUDIR}"

if [ "$DIRPATH" == "/" -o "$DIR" == "/" ]; then
    echo "Error: Parent directory or calculated /Path/COU is '/'"
    exit 2
fi

# verify directory does not exist
if [ -d "$DIRPATH" ]; then
    echo "COU directory $DIRPATH exists:  No action taken."
    exit 0
fi 

# timeout with KILL (9) after 10 seconds in case the mount is hanging
STAT=$(timeout -s 9 10 stat -f -c %T $DIR)

if [ "$STAT" == "ceph" ]; then
    mkdir "$DIRPATH"

    if [ $? -ne 0 ]; then
        echo "Error: mkdir failed for $DIRPATH"
        exit 4
    fi
else
    echo "Error: Parent $DIR does not stat as ceph filesystem"
    exit 3
fi

AdminGroup="CO_COU_${COU}_admins"
MemberGroup="CO_COU_${COU}_members_active"

chmod g+s "$DIRPATH" && \
chmod g+w "$DIRPATH" && \
chmod o-rwx "$DIRPATH" && \
chgrp "$AdminGroup" "$DIRPATH" && \
setfacl -d -m "group:$MemberGroup:rx" "$DIRPATH"

if [ $? -ne 0 ]; then
    echo "Error: setfacl failed to set group read ACL group:$MemberGroup:rx"
    exit 5
fi

setfattr  -n ceph.dir.layout.pool -v "cou.${COU}.fs" "$DIRPATH"

if [ $? -ne 0 ]; then 
    echo "Error: setfattr failed to set ceph.dir.layout.pool cou.${COU}.fs"
    exit 5
fi

# shell command reference
# chmod g+s nightlights/
# chmod g+w nightlights/
# chgrp OSiRIS:Nightlights:CO_COU_Nightlights_admins nightlights
# setfacl -d -m group:1000260:rx nightlights

 # setfattr  -n ceph.dir.layout.pool -v cou.Jetpack.fs jetpack

 # escaped group for setfacl
 # default:group:OSiRIS\072Nightlights\072CO_COU_Nightlights_members_active:r-x
