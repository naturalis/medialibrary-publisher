#!/bin/bash

# Auto snapshot and auto snapshot destory based on diskspace
# Made for medialibrary Naturalis
# Uses zfs
# (r) Atze de Vries 2013

# Enter the maximum diskspace which may be used 
disk_used_limit=5

# Other static options
volume_name=medialib
pool_name=data
max_snapshot_deletes=4
deletes=0
current_snapshot_date=`date +"%Y.%m.%d.%k.%M"`

# make sure script is run as root
if [ "$(id -u)" != "0" ]
then
	echo "This script must be run as root" 1>&2
	exit 1
fi

disk_used=$(zpool list $pool_name  -o cap | grep %)
disk_used=${disk_used//[%]/}
snapshots=$(zfs list -t snapshot -o name | grep $pool_name/$volume_name -m 1)


while [ $disk_used -ge $disk_used_limit ] 
do
	echo 'ooh nooossss! much space is used!!'
	echo 'deleting the following snapshot: '$snapshots
	echo 'zfs destroy '$snapshots
	
	deletes=$(($deletes + 1))
	echo 'number of deletes: '$deletes
	if [ $deletes -ge $max_snapshot_deletes ] 
	then
		break
	fi

	disk_used=$(zpool list $pool_name  -o cap | grep %)
	disk_used=${disk_used//[%]/}
	snapshots=$(zfs list -t snapshot -o name | grep $pool_name/$volume_name -m 1)
	
	sleep 2
done


echo "new snapshot snapshot command: zfs snapshot $pool_name/$volume_name@$current_snapshot_date"


