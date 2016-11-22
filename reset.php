<?php

/**
 * Utility script. Will empty the database and initialize a
 * folder structure, including some test images, so you can
 * test the harvesting cycle: harvest, offload, publish-masters,
 * publish-www, cleanup.
 * 
 * ********************************************************
 * DO NOT RUN IN PRODUCTION AS IT WILL WIPE OUT EVERYTHING
 *              RELATED TO THE MEDIA LIBRARY
 * ********************************************************
 * 
 * @author Ayco Holleman
 */
$PROD_IPS = array(
		'10.21.1.111',
		'nnms111.nnm.local'
);

// Try to prevent this script from being run on production
if(in_array(php_uname('n'), $PROD_IPS)) {
	die('no can do');
}


set_include_path('.');

include 'autoload.php';

use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\Command;

// The top directory containing the harvest folder,
// the staging folder, the duplicates folder, etc.
$top = $argv[1];
// A folder containing a set of test images
$bak = $argv[2];
// Also empty the media library database?
$resetDb = isset($argv[3]) && $argv[3] === 'true' ? true : false;

$verbose = true;

try {
	
	$context = new Context(new Config('config.ini'));
	
	// Try to prevent this script from being run on production
	if(in_array($context->getConfig()->db0->host, $PROD_IPS)) {
		die('no can do');
	}
	
	if($resetDb) {
		$pdo = $context->getSharedPDO();
		echo "\nDeleting media records";
		$pdo->query('DELETE FROM media');
		echo "\nDeleting deleted_media records";
		$pdo->query('DELETE FROM deleted_media');
		echo "\nDeleting tar_file records";
		$pdo->query('DELETE FROM tar_file');
	}
	
	FileUtil::deleteRecursiveUnder($top . 'harvest', $verbose);
	FileUtil::deleteRecursiveUnder($top . 'staging', $verbose);
	FileUtil::deleteRecursiveUnder($top . 'masters', $verbose);
	FileUtil::deleteRecursiveUnder($top . 'www', $verbose);
	FileUtil::copyRecursiveUnder($bak, $top . 'harvest');
}

catch(\Exception $e) {
	//echo $e->getTraceAsString();
	echo "\n" . $e->getFile() . ' line ' . $e->getLine() . ': ';
	echo "\n" . $e->getMessage();
}