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

class StagingAreaCleaner {
	

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
		
		$this->_logger->addInfo("Staging area inspected for this run: \"$stagingDirectory\"");
		if(!is_dir($stagingDirectory)) {
			$this->_logger->addWarning("No staging area at configured location (\"$stagingDirectory\")");
			return true;
		}
		
		$today = (int) date('Ymd');		

		$minDaysOld = $this->_context->getConfig()->cleaner->minDaysOld;
		if($minDaysOld === null || !is_numeric($minDaysOld)) {
			throw new Exception('Option minDaysOld must be set to an integer value');
		}
		$minDaysOld = max((int) $minDaysOld, 0);
				

		$clean = true;
		// Directly under the root directory of the staging area we should
		// find the tar area, and for the rest only directories named after
		// a particular date (using date format "YmdHis").
		// See StagingAreaManager::createStagingArea()
		foreach(FileUtil::scandir($stagingDirectory) as $dateDir) {
			PublisherObject::checkPanicFile($panicFile);
			if($dateDir === '.' || $dateDir === '..') {
				continue;
			}
			if($dateDir === TarAreaManager::TAR_AREA_DIR) {
				$this->_logger->addDebug("Ignoring tar area (will be cleaned separately): $dateDir");
				continue;
			}
			$fullPath = $stagingDirectory . DIRECTORY_SEPARATOR . $dateDir;
			if(!is_dir($fullPath) || !is_numeric($dateDir)) {
				throw new Excepion("Not expected at this location: $fullPath");
			}
			$dateCreated = (int) substr($dateDir, 0, 8);
			if(($today - $dateCreated) < $minDaysOld) {
				$this->_logger->addInfo("Ignoring $dateDir (less than $minDaysOld days old)");
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
		$this->_logger->addInfo("Cleaning staging area for date $dateString (folder: $date)");
		
		if(FileUtil::isEmptyDir($dateDir)) {
			$this->_logger->addInfo("Empty staging area (will be removed)");
			$this->_rmDir($dateDir);
			return;
		}
		
		$clean = true;
		$sweep = $this->_context->getConfig()->getBoolean('cleaner.sweep');
		$foundAlienStagingArea = false;
		
		foreach(FileUtil::scandir($dateDir) as $producerDir) {
			if($producerDir === '.' || $producerDir === '..') {
				continue;
			}
			if(!$sweep && ($producerDir !== $this->_context->getConfig()->producer)) {
				$this->_logger->addInfo("Encountered and ignored staging area for another producer: $producerDir");
				$foundAlienStagingArea = true;
				continue;
			}
			$fullPath = $dateDir . DIRECTORY_SEPARATOR . $producerDir;
			if(!is_dir($fullPath)) {
				$this->_logger->addWarning("File found at unexpected location in staging area $dateDir: $producerDir.");
				$clean = false;
				continue;
			}
			if(!$this->_cleanProducerDirectory($fullPath)) {
				$clean = false;
			}
		}
		
		if($clean) {
			if(!$foundAlienStagingArea) {
				$this->_rmDir($dateDir);
				$this->_logger->addInfo("Successfully cleaned and removed staging area for date $dateString");
			}
			else {
				$this->_logger->addInfo("Staging area for date $dateString was not removed because it was also used for other producers than {$this->_context->getConfig()->producer}.");
			}
		}
		else {
			$this->_logger->addWarning("Did not remove staging area for date $dateString");
		}
		
		return $clean;
	}
	
	// sprintf template
	const MSG_NOT_REMOVED = 'Cannot remove %s. backup_ok=%s; master_ok=%s; www_ok=%s';


	private function _cleanProducerDirectory($producerDir)
	{
		$producer = basename($producerDir);
		$this->_logger->addInfo("Cleaning staging area for producer $producer");
		$phase2Dir = FileUtil::createPath($producerDir, 'phase2');
		if(!is_dir($phase2Dir)) {
			$this->_logger->addWarning('No \"phase2\" directory found for this producer. Cannot proceed');
			return false;
		}
		$clean = true;
		$dateDir = basename(dirname($producerDir));
		$dateCreated = (int) substr($dateDir, 0, 8);
		foreach(FileUtil::scandir($phase2Dir) as $fileName) {
			if($fileName === '.' || $fileName === '..') {
				continue;
			}
			$fullPath = $phase2Dir . DIRECTORY_SEPARATOR . $fileName;
			$dashPosh = strpos($fileName, '-');
			if($dashPosh === false) {
				// This cannot be a file that was put here by the media library,
				// because all media files should have a database id, followed by
				// a dash, prefixed to their original name. We will consider the
				// file to be junk and removable.
				$this->_logger->addWarning("File found at unexpected location in staging area: $producerDir.");
				continue;
			}
			$mediaId = substr($fileName, 0, $dashPosh);
			$status = $this->_dao->getStatus($mediaId);
			if($status === false) {
				// Some other process has removed the database record for this file
				// It has become an orphan, and can be removed from the staging area
				$this->_logger->addDebug("Marked for deletion: $fileName. No matching database record found.");
				continue;
			}
			$sourceFileCreated = (int) $status->source_file_created;
			if(($sourceFileCreated - 7) > $dateCreated) {
				// There is a newer version of this file. We keep the file for
				// one more week. Then we consider it marked for deletion.
				$this->_logger->addDebug("Marked for deletion: $fileName. A new version has been harvested and indexed.");
				continue;
			}
			if($status->backup_ok == 1 && $status->master_ok == 1 && $status->www_ok == 1) {
				// Everything went just fine with the media file; it has reached
				// its final destinations and can be removed from the staging area
				continue;
			}
			else {
				$warning = sprintf(self::MSG_NOT_REMOVED, $fileName, $status->backup_ok, $status->master_ok, $status->www_ok);
				$this->_logger->addWarning($warning);
			}
			$clean = false;
		}
		if($clean) {
			// OK, we are going to remove the ENTIRE directory for this producer;
			// which not only contains the "phase2" directory, but also the "phase1",
			// "buckets" and "tars" directories.
			$this->_rmDir($producerDir);
			$this->_logger->addInfo("Successfully cleaned and removed staging area for producer $producer");
		}
		else {
			$this->_logger->addWarning("Did not remove staging area for producer $producer ($producerDir)");
		}
		return $clean;
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on cleaning staging area: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
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

}