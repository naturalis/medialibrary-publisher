<?php

/**
 * A driver script for the backup process.
 * 
 * @author ayco_holleman
 */

// Adjust time zone as appropriate
date_default_timezone_set('Europe/Amsterdam');

set_include_path('.');
define('APPLICATION_PATH', __DIR__);
include 'autoload.php';

use nl\naturalis\medialib\publisher\Offloader;


if (!isset($argv[1])) {
	echo 'Please specify a configuration file';
	exit(1);
}

if (!isset($argv[2]) || !is_numeric($argv[2])) {
	echo 'Please specify a backup group';
	exit(1);
}

$iniFile = $argv[1];
$backupGroup = $argv[2];

if (!is_file($iniFile)) {
	echo "No such file: $iniFile";
	exit(1);
}

$offloader = new Offloader($iniFile, $backupGroup);
$offloader->run();