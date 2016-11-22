<?php

namespace nl\naturalis\medialib\publisher\clean;

use \Exception;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\offload\TarAreaManager;
use nl\naturalis\medialib\publisher\db\dao\CleanerDAO;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\Command;
use Monolog\Logger;

class TarAreaCleaner {
	

	/**
	 *
	 * @var Context
	 */
	private $_context;
	
	/**
	 *
	 * @var Logger
	 */
	private $_logger;
	
	/**
	 *
	 * @var Config
	 */
	private $_config;
	
	/**
	 *
	 * @var CleanerDAO
	 */
	private $_dao;


	public function __construct(Context $context)
	{
		$this->_context = $context;
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new CleanerDAO($context);
	}


	public function cleanup()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		$startTime = time();
		try {
			$clean = $this->_cleanDateDirectories();
			$this->_logStatistics($startTime);
			return $clean;
		}
		catch(Exception $e) {
			$this->_logStatistics($startTime);
			throw $e;
		}
	}


	private function _cleanDateDirectories()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		$stagingDirectory = $this->_context->getConfig()->stagingDirectory;
		$tarArea = FileUtil::createPath($stagingDirectory, TarAreaManager::TAR_AREA_DIR);
		
		$this->_logger->addInfo("Tar area inspected for this run: \"$tarArea\"");
		if(!is_dir($tarArea)) {
			$this->_logger->addWarning("No tar area at configured location (\"$tarArea\")");
			return true;
		}
		
		$today = (int) date('Ymd');
		
		$minDaysOld = $this->_context->getConfig()->cleaner->minDaysOld;
		if($minDaysOld === null || !is_numeric($minDaysOld)) {
			throw new Exception('Option minDaysOld must be set to an integer value');
		}
		$minDaysOld = max((int) $minDaysOld, 0);
		
		$clean = true;
		// Directory under the root of the tar area we should find directories
		// named after a particular date (using date format "YmdHis"). Under
		// these directories we should find one directory for each backup group. 
		// See TarAreaManager::createTarArea().
		foreach(FileUtil::scandir($tarArea) as $dateDir) {
			PublisherObject::checkPanicFile($panicFile);
			if($dateDir === '.' || $dateDir === '..') {
				continue;
			}
			$fullPath = $tarArea . DIRECTORY_SEPARATOR . $dateDir;
			if(!is_dir($fullPath) || !is_numeric($dateDir)) {
				throw new Excepion("Not expected at this location: $fullPath");
			}
			$dateCreated = (int) substr($dateDir, 0, 8);
			if(($today - $dateCreated) < $minDaysOld) {
				continue;
			}
			if(!$this->_cleanDateDirectory($fullPath)) {
				$clean = false;
			}
		}
		
		return $clean;
	}


	private function _cleanDateDirectory($dateDir)
	{
		$date = basename($dateDir);
		$y = substr($date, 0, 4);
		$m = substr($date, 4, 2);
		$d = substr($date, 6, 2);
		$h = substr($date, 8, 2);
		$i = substr($date, 10, 2);
		$s = substr($date, 12, 2);
		$dateString = "{$y}-{$m}-{$d} {$h}:{$i}:{$s}";
		$this->_logger->addInfo("Cleaning tar area for date $dateString (folder: $date)");
		
		if(FileUtil::isEmptyDir($dateDir)) {
			$this->_logger->addInfo("Empty staging area (will be removed)");
			$this->_rmDir($dateDir);
			return;
		}
		
		$clean = true;
		foreach(FileUtil::scandir($dateDir) as $backupGroupDir) {
			if($backupGroupDir === '.' || $backupGroupDir === '..') {
				continue;
			}
			// The full path to a "backupgroup" directory
			$fullPath = FileUtil::createPath($dateDir, $backupGroupDir);
			// The full path to the "buckets" directory beneath it
			$bucketRoot = FileUtil::createPath($fullPath, TarAreaManager::BUCKETS_DIR);
			if($this->_checkBuckets($bucketRoot)) {
				// All media in all buckets were successfully backed up.
				// Delete the entire "backupgroup" directory, containing
				// the buckets and the tar files created from them
				$this->_rmDir($fullPath);
			}
			else {
				$clean = false;
			}
		}
		return $clean;
	}


	private function _checkBuckets($bucketRoot)
	{
		$this->_logger->addDebug("Scanning $bucketRoot");
		foreach(FileUtil::scandir($bucketRoot) as $dir) {
			if($dir === '.' || $dir === '..') {
				continue;
			}
			$path = FileUtil::createPath($bucketRoot, $dir);
			if(!$this->_checkBucket($path)) {
				return false;
			}
		}
		return true;
	}


	private function _checkBucket($bucket)
	{
		$this->_logger->addDebug("Scanning $bucket");
		// forexample: __TAR_AREA__/20140305081011/backupgroup000/buckets/0001
		$dateDir = basename(dirname(dirname(dirname($bucket))));
		$dateCreated = (int) substr($dateDir, 0, 8);
		foreach(FileUtil::scandir($bucket) as $file) {
			if($file === '.' || $file === '..') {
				continue;
			}
			$status = $this->_getStatus($file);
			if($status === null) {
				continue;
			}
			if($status === false) {
				// Some other process has removed the database record for this file
				// It has become an orphan, and can be removed from the staging area
				$this->_logger->addDebug("Marked for deletion: $file. No matching database record found.");
				continue;
			}
			$sourceFileCreated = (int) $status->source_file_created;
			if(($sourceFileCreated - 7) > $dateCreated) {
				// There is a newer version of this file. We keep the file for
				// one more week. Then we consider it marked for deletion.
				$this->_logger->addDebug("Marked for deletion: $fileName. A new version has been harvested and indexed.");
				continue;
			}
			if($status->backup_ok == 0) {
				return false;
			}
		}
		return true;
	}


	private function _rmDir($dir)
	{
		if($this->_context->getConfig()->getBoolean('cleaner.unixRemove')) {
			$cmd = new Command('rm -rf "' . $dir . '"');
			if($cmd->execute() != 0) {
				$this->_logger->addDebug($cmd->getOutputAsString());
				throw new Exception("Could not delete directory $dir");
			}
		}
		else {
			FileUtil::deleteRecursive($dir);
		}
	}
	

	private function _getStatus($fileName)
	{
		$dashPosh = strpos($fileName, '-');
		if($dashPosh === false) {
			// This cannot be a file that was put here by the media library,
			// because all media files should have a database id, followed by
			// a dash, prefixed to their original name. We will consider the
			// file to be junk and removable.
			$this->_logger->addError("Encountered rogue file in medialib managed directory: " . $fileName);
			return null;
		}
		$mediaId = substr($fileName, 0, $dashPosh);
		return $this->_dao->getStatus($mediaId);
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on cleaning tar area: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}