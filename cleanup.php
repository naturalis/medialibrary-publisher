<?php

/**
 * A driver script for process that cleans up the staging areas.
 * 
 * @author ayco_holleman
 */

date_default_timezone_set('Europe/Amsterdam');
set_include_path('.');
define('APPLICATION_PATH', __DIR__);
include 'autoload.php';

use nl\naturalis\medialib\publisher\Cleaner;


if (!isset($argv[1])) {
	echo 'Please specify a configuration file';
	exit(1);
}

$iniFile = $argv[1];
if (!is_file($iniFile)) {
	echo "No such file: $iniFile";
	exit(1);
}

$cleaner = new Cleaner($iniFile);
$cleaner->run();