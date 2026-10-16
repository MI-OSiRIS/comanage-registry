#!/bin/bash

# This is a helper script meant to be run with sudo as a user having
# permissions to create COU dirs and set permissions, placement, etc

# Requires arguments: mkCopHomedir.sh [COPerson Username]  [parent directory to create COU dir] (must be cephFS)

# exit codes:
# 0 success or directory exists 
# (this script does not do permission fixups in case they are intentionaly changed after provisioning)
# 2 bad option or option arg doesn't match required format
# 3 mount did not stat as type ceph
# 4 mkdir failed
# 5 setting directory ownership or permissions failed


function usage {
    echo "Usage: mkCopHomedir.sh [COPerson Username]  [parent directory path for Coperson homedir]    (must be cephFS)"
}


if [ -n "$1" ]; then
    COP=$1
else 
    echo "Error: Empty string not allowed for COP username argument"
    usage
    exit 2
fi

if [ -n "$2" ]; then
    if [[ $2 == *'/'* ]]; then
        DIR=$2
    else
       echo "Error: Parent directory does not contain '/', does not appear to be path"
       usage
       exit 2 
   fi
else
    echo "Error: Empty string not allowed for parent directory argument"
    usage
    exit 2
fi

COPDIR=$(echo $COP | tr "[:upper:]" "[:lower:]")
DIRPATH="${DIR}/${COPDIR}"

if [ "$DIRPATH" == "/" -o "$DIR" == "/" ]; then
    echo "Error: Parent directory or calculated /Path/COP is '/'"
    exit 2
fi

Member=$COPDIR
MemberGroup=$COPDIR

# verify directory does not exist
if [ -d "$DIRPATH" ]; then
    echo "COP directory $DIRPATH exists:  No action taken."
    exit 0
fi 

# timeout with KILL (9) after 10 seconds in case the mount is hanging
STAT=$(timeout -s 9 10 stat -f -c %T $DIR)

#if [ "$STAT" == "ceph" -o "$STAT" == "fuseblk" ]; then
if [ "$STAT" == "ceph" ]; then
    mkdir "$DIRPATH"

    if [ $? -ne 0 ]; then
        echo "Error: mkdir failed for $DIRPATH"
        exit 4
    else
        chmod g-rwx "$DIRPATH" && \
        chmod o-rwx "$DIRPATH" && \
        chown "$Member":"$MemberGroup" "$DIRPATH"

	if [ $? -ne 0 ]; then
           echo "Error: setting the ownership failed for $DIRPATH"
           exit 5
        fi    
    fi
else
    echo "Error: Parent $DIR does not stat as ceph filesystem"
    exit 3
fi

