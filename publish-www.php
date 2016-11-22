<?php

/**
 * A driver script for the process publishes the master files
 * (i.e. puts them in a location from where they can be served
 * by the media server)
 * 
 * @author ayco_holleman
 */

// Adjust time zone as appropriate
date_default_timezone_set('Europe/Amsterdam');

set_include_path('.');
define('APPLICATION_PATH', __DIR__);
include 'autoload.php';

use nl\naturalis\medialib\publisher\WebPublisher;


if(!isset($argv[1])) {
	echo 'Please specify a configuration file';
	exit(1);
}

$iniFile = $argv[1];
if(!is_file($iniFile)) {
	echo "No such file: $iniFile";
	exit(1);
}

$publisher = new WebPublisher($iniFile);
$publisher->run();