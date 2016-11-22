#!/bin/bash


# Het is belangrijk dat het harvest configuratie bestand
# in de map config_dir staat
# het maakt niet uit hoe het bestand heet. De rest van de info
# wordt uit de ini gehaald
# Zodra het ini in de config_dir staat wordt hij meegenomen in het
# harvest process.

# Digisstraat producer namen

# Map en file lokaties
config_dir=/home/medialib/config-harvest/*
# harvest_ini=/home/medialib/config-test/harvest
publish_dir=/home/medialib/publisher
stage_dir=/data/medialib/staging 
echo_name="STARTPUBLISH.SH"
declare -a producer_array
declare -a ini_array
declare -a harvest_dirs


# gather some info from ini's
for ini in $config_dir; do
	harvest_dir_raw=`sed -n 's/.*harvestDirectory *= *\([^ ]*.*\)/\1/p' < $ini`
	producer_raw=`sed -n 's/.*producer *= *\([^ ]*.*\)/\1/p' < $ini`
	l_h=$[${#harvest_dir_raw} -3]
	l_p=$[${#producer_raw} -3]
	harvest_dir="${harvest_dir_raw:1:$l_h}"
	harvest_dirs[${#harvest_dirs[@]}]=$harvest_dir
	producer_array[${#producer_array[@]}]=${producer_raw:1:$l_p}
done


# for each straat start harvesting process
# do this before checking dirs to produce 
# if this is the care an cannot find directory error 
# by mail through the harvesting process
for ini in $config_dir; do
	cmd="$publish_dir/harvest.php $ini $stage_dir"
	echo $cmd
	php $cmd &
	echo $echo_name": waiting 5 seconds for next straat"
	sleep 5
done


# do directory and simlink check
# create directorys or simlink if
# it doesn't exists
echo ${#harvest_dirs[@]}
for ((i=0; i<${#harvest_dirs[@]}; i++)) do
	#check harvest dir
	echo $i
	echo ${harvest_dirs[$i]}
	if [ ! -d ${harvest_dirs[$i]} ]
	then
		mkdir ${harvest_dirs[$i]}
	fi

	#check dead-image dir
	dead_image_dir=${harvest_dirs[$i]}/../errors
	if [ ! -d $dead_image_dir ]
	then
		mkdir $dead_image_dir
	fi
	
	#check production dir
	production_dir=${harvest_dirs[$i]}/../production
	if [ ! -d $production_dir ]
	then
		mkdir $production_dir
	fi
	simlink=/data/dead-images/${producer_array[$i]} 
	if [ ! -L $simlink ]
	then
		echo ln -s "$dead_image_dir" $simlink
		
		ln -s "$dead_image_dir" $simlink
	fi
done


echo $echo_name": waiting 300 seconds to populate TIFF"
sleep 300

for straat in ${producer_array[@]}; do
	cmd="$publish_dir/publish-masters.php $straat"
	echo $cmd
	php $cmd &
	echo $echo_name": waiting 5 seconds for next straat"
	sleep 5
done

echo $echo_name": waiting 900 seconds to populate masters" 
sleep 900

# check if there is a master process running
while  ps aux | grep -v grep | grep publish-masters.php > /dev/null    
do
	for straat in ${producer_array[@]}; do
		proc_www="publish-www.php $straat"
		proc_mas="publish-masters.php $straat"
		echo $proc_www
		echo $proc_mas
		# check for 'straat' if publish-www 'straat' is running, does nothing if running
		if ps aux | grep -v grep | grep "$proc_www" > /dev/null ; then
			echo $echo_name": publish-www "$straat" is still running"
		else
			# check for 'straat' if publish-master 'straat' is running, if running start publish-www 'straat' else do nothing
			if ps aux | grep -v grep | grep "$proc_mas" > /dev/null ; then
				cmd="$publish_dir/publish-www.php $straat"
				echo $cmd
				php $cmd &
			fi
		fi
	
		echo $echo_name": waiting 5 seconds for next straat"
		sleep 5
	done
	
	echo $echo_name": Waiting 900 secondes for next while www process loop" 
	sleep 900
	echo $echo_name": Next while loop"
done

echo $echo_name": Waiting 300 seconds before starting final publish-www" 
sleep 300
echo $echo_name": running publish www for the last time after publish-masters has finished"

for straat in ${producer_array[@]}; do
	cmd="$publish_dir/publish-www.php $straat"
	echo $cmd
	php $cmd &
	echo $echo_name": waiting 5 seconds for next straat"
	sleep 5
done


