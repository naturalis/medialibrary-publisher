<?php

namespace nl\naturalis\medialib\publisher\offload;

use \Exception;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\publisher\db\dao\HarvesterDAO;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\Config;
use Monolog\Logger;
use nl\naturalis\medialib\publisher\PublisherObject;

/**
 * This class copies media files from the staging area to the tar area, where
 * they will be tarred and sent to the backup server. Within the tar area a
 * number of subdirectories ("buckets") will be created, each containing as many
 * media files as possible given the maximum size of the tar files (as set in
 * the configuration file). These buckets are tarred in one shot; for each
 * bucket one tar file is created. This is because tarring directories is
 * substantially faster than adding individual media files one by one to a tar
 * file.
 *
 * @author ayco_holleman
 */
class TarAreaManager {
	
	/**
	 * The subdirectory under $this->_config->stagingDirectory used for created
	 * buckets and tar files.
	 */
	const TAR_AREA_DIR = '__TAR_AREA__';
	/**
	 * Directory (under TAR_AREA_DIR) under which the buckets are created.
	 */
	const BUCKETS_DIR = 'buckets';
	/**
	 * Directory (under TAR_AREA_DIR) in which the tar files are placed.
	 */
	const TARS_DIR = 'tars';
	
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
	 * @var HarvesterDAO
	 */
	private $_dao;
	private $_backupGroup;
	private $_workspace;
	private $_bucketsDir;
	private $_tarsDir;
	private $_initialized = false;


	public function __construct(Context $context, $backupGroup)
	{
		$this->_context = $context;
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new HarvesterDAO($context);
		$this->_backupGroup = $backupGroup;
	}


	/**
	 * Creates the folder structure for the tar area if it does not
	 * exist already.
	 */
	public function createTarArea()
	{
		$time = $this->_context->getRequiredProperty('start');
		$stagingArea = $this->_config->stagingDirectory;
		$tarArea = FileUtil::mkdir($stagingArea, self::TAR_AREA_DIR, false);
		$dateDir = FileUtil::mkdir($tarArea, date('YmdHis', $time), false);
		$bg = 'backupgroup' . str_pad($this->_backupGroup, 3, '0', STR_PAD_LEFT);
		$this->_workspace = FileUtil::mkdir($dateDir, $bg, false);
		$this->_bucketsDir = FileUtil::mkdir($this->_workspace, self::BUCKETS_DIR, false);
		$this->_tarsDir = FileUtil::mkdir($this->_workspace, self::TARS_DIR, false);
		$this->_initialized = true;
	}


	/**
	 * Get the full path to the directory in which today's work for
	 * this {@code TarAreaManager}'s backup group takes place. Under this
	 * directory there will be a subdirectory named "buckets", and a
	 * subdirectory name "tars".
	 */
	public function getWorkspace()
	{
		if(!$this->_initialized) {
			throw new Exception('Tar area not initialized yet');
		}
		return $this->_workspace;
	}


	/**
	 * Get the full path to the directory in which the buckets are created.
	 */
	public function getBucketsDirectory()
	{
		if(!$this->_initialized) {
			throw new Exception('Tar area not initialized yet');
		}
		return $this->_bucketsDir;
	}


	/**
	 * Get the full path to the directory in which the tar files are placed.
	 */
	public function getTarsDirectory()
	{
		if(!$this->_initialized) {
			throw new Exception('Tar area not initialized yet');
		}
		return $this->_tarsDir;
	}


	public function moveMediaToBuckets()
	{
		
		// Was the process interrupted before we even go this far?
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		

		if(!$this->_initialized) {
			throw new Exception('Tar area not initialized yet');
		}
		
		$this->_logger->addInfo("Moving media to tar area ({$this->_bucketsDir})");
		
		$startTime = time();
		
		// maximum size of tar file
		$maxSize = (((int) $this->_config->offload->tar->maxSize) * 1024 * 1024);
		// maximum number of files in tar file
		$maxFiles = (int) $this->_config->offload->tar->maxFiles;
		
		// total size of all files being moved
		$totalSize = 0;
		// total number of files being moved
		$totalFiles = 0;
		
		// number of files in current subdirectory
		$files = 0;
		// total size of files in current subdirectory
		$size = 0;
		
		// current subdirectory in tar area
		$bucket = null;
		
		try {
			
			$this->_logger->addDebug("Retrieving media for backup group {$this->_backupGroup}");
			$stmt = $this->_dao->getOffloadableMedia($this->_backupGroup);
			while($media = $stmt->fetch()) {
				PublisherObject::checkPanicFile($panicFile);
				$path = $media->source_file;
				if(!is_file($path)) {
					$this->_logger->addWarning("Stale database record (no such file: \"$path\"). Record will be removed from media database.");
					$this->_dao->deleteMedia($media->id);
					continue;
				}
				++$totalFiles;
				$fz = filesize($path);
				$size += $fz;
				$totalSize += $fz;
				if($bucket === null) {
					$bucket = $this->_createBucket();
				}
				else if($size > $maxSize || ++$files === $maxFiles) {					
					$bucket = $this->_createBucket();
					$size = 0;
					$files = 0;
				}
				if($this->_config->offload->method !== 'PHP') {
					// Create a symbolic link in the bucket directory to media file
					// in the phase2 directory
					FileUtil::symlink($path, $bucket . DIRECTORY_SEPARATOR . basename($path));
				}
				else {
					// We are going to use the PharData class in the TarFileCreator
					// class, and in spite of what it says on php.net this class will
					// not follow symlinks.
					FileUtil::copy($path, $bucket . DIRECTORY_SEPARATOR . basename($path));
				}
			}
		}
		catch(Exception $e) {
			$this->_logStatistics($startTime, $totalSize, $totalFiles);
			throw $e;
		}
		
		$this->_logStatistics($startTime, $totalSize, $totalFiles);
		
		return $totalFiles;
		
	}
	

	// keeps track of the number of buckets created; each
	// bucket will be named according to this value
	private $_bucketCounter = 0;


	private function _createBucket()
	{
		$subdir = str_pad(++$this->_bucketCounter, 4, '0', STR_PAD_LEFT);
		return FileUtil::mkdir($this->_bucketsDir, $subdir);
	}
	
	// Check that the pid in the panic is the same as the pid
	// of the process within which this TarFileCreator runs.
	// Pretty spooky if it is not.
	private function _validatePanicFile($panicFile)
	{
		$pid = file_get_contents($panicFile);
		if($pid != getmypid()) {
			$error = "Another Offloader (pid: $pid) is still busy processing media files for ";
			$error .= "backup group {$this->_backupGroup}. If you are sure this is not the case, ";
			$error .= "delete file $path and start again.";
			throw new \Exception($error);
		}
	}


	private function _logStatistics($startTime, $totalSize, $totalFiles)
	{
		$totalSize = round(($totalSize / (1024 * 1024)), 2);
		$this->_logger->addInfo("Total number of files moved to tar area: $totalFiles");
		$this->_logger->addInfo("Total size of files moved to tar area: $totalSize MB");
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on populating tar area: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}
