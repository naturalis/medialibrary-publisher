<?php

namespace nl\naturalis\medialib\publisher\offload;

use \Exception;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\exception\UserInterruptException;
use nl\naturalis\medialib\publisher\db\dao\HarvesterDAO;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\Command;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\Config;
use Monolog\Logger;

class TarFileCreator {
	
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
	/**
	 *
	 * @var RemoteStorageManager
	 */
	private $_remoteStorageManager;
	private $_bucketsDir;
	private $_tarsDir;
	private $_backupGroup;


	public function __construct(Context $context)
	{
		$this->_context = $context;
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new HarvesterDAO($context);
	}


	/**
	 * Set directory containing the buckets.
	 */
	public function setBucketsDirectory($dir)
	{
		$this->_bucketsDir = $dir;
	}


	/**
	 * Set the directory into which to place the tar files.
	 */
	public function setTarsDirectory($dir)
	{
		$this->_tarsDir = $dir;
	}


	public function setBackupGroup($backupGroup)
	{
		$this->_backupGroup = $backupGroup;
	}


	public function setRemoteStorageManager(RemoteStorageManager $rsm)
	{
		$this->_remoteStorageManager = $rsm;
	}


	public function createTarFiles()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		$offloadImmediate = $this->_config->getBoolean('offload.immediate');
		$offloadMethod = $this->_config->offload->method;
		
		try {
			$remoteDirAbsolute = null;
			if($offloadImmediate || $offloadMethod === 'CUSTOM') {
				$remoteDirAbsolute = $this->_remoteStorageManager->getRemoteDirectoryAbsolutePath();
				$this->_logger->addInfo('Creating and offloading tar files');
			}
			else {
				$this->_logger->addInfo('Creating tar files');
			}
			foreach(FileUtil::scandir($this->_bucketsDir) as $bucket) {
				PublisherObject::checkPanicFile($panicFile);
				if($bucket === '.' || $bucket === '..') {
					continue;
				}
				switch ($offloadMethod) {
					case 'UNIX' :
						$this->_doUnixTar($bucket);
						break;
					case 'CUSTOM' :
						$this->_doCustomTar($bucket, $remoteDirAbsolute);
						break;
					case 'PHP' :
						$this->_doPHPTar($bucket);
						break;
					default :
						throw new Exception("Invalid value for setting offload.method: \"{$this->_config->offload->method}\". Select PHP, UNIX or CUSTOM.");
				}
			}
		}
		catch(Exception $e) {
			$this->_logStatistics($startTime);
			$this->_remoteStorageManager->closeConnection();
			throw $e;
		}
		
		$this->_logStatistics($startTime);
	}


	private function _doUnixTar($bucket)
	{
		$bucketPath = FileUtil::createPath($this->_bucketsDir, $bucket);
		if(FileUtil::isEmptyDir($bucketPath)) {
			$this->_logger->addWarning("Ignoring empty bucket: $bucketPath");
			return;
		}
		$tarFile = $this->_getTarFileName($bucket);
		$tarPath = FileUtil::createPath($this->_tarsDir, $tarFile);
		$this->_logger->addDebug("Bucket: $bucketPath");
		$this->_logger->addDebug("Tar file: $tarPath");
		$commandLine = sprintf('tar -h -cf "%s" "%s"', $tarPath, $bucketPath);
		$command = new Command($commandLine);
		if($command->execute() != 0) {
			$this->_logger->addDebug("Command issued: " . $command->getCommandLine());
			throw new Exception("Failed to create tar file $tarFile for bucket $bucket: " . $command->getOutputAsString());
		}
		$remoteDir = $this->_remoteStorageManager->getRemoteDirectoryRelativePath();
		$tarFileId = $this->_dao->registerTarFile($tarFile, $remoteDir);
		if($this->_config->getBoolean('offload.immediate')) {
			$this->_remoteStorageManager->send($tarFile);
			if(!$this->_remoteStorageManager->hasArrived($tarFile)) {
				throw new Exception('Tar file did not arrive on remote server: ' . $tarFile);
			}
			$this->_setTarFile($bucketPath, $tarFileId, true);
		}
		else {
			$this->_setTarFile($bucketPath, $tarFileId, false);
		}
	}


	private function _doCustomTar($bucket, $remoteDirAbsolute)
	{
		$bucketPath = FileUtil::createPath($this->_bucketsDir, $bucket);
		if(FileUtil::isEmptyDir($bucketPath)) {
			$this->_logger->addWarning("Ignoring empty bucket: $bucketPath");
			return;
		}
		$tarFile = $this->_getTarFileName($bucket);
		$tarPath = FileUtil::createPath($this->_tarsDir, $tarFile);
		$this->_logger->addDebug("Bucket: $bucketPath");
		$this->_logger->addDebug("Tar file: $tarPath");
		$commandLine = $this->_config->offload->command;
		$commandLine = str_replace('%local_dir%', $bucketPath, $commandLine);
		$commandLine = str_replace('%remote_dir%', $remoteDirAbsolute, $commandLine);
		$commandLine = str_replace('%name%', $tarFile, $commandLine);
		// Since the command will contain command line parameters for the
		// FTP-login, we only echo it, and only when in debug mode.
		if($this->_config->logging->level === 'DEBUG') {
			echo "\nCommand: $commandLine\n";
		}
		$command = new Command($commandLine);
		if($command->execute() != 0) {
			throw new Exception("Failed to execute $commandLine: " . $command->getOutputAsString());
		}
		if(!$this->_remoteStorageManager->hasArrived($tarFile)) {
			throw new Exception('Tar file did not arrive on remote server: ' . $tarFile);
		}
		$remoteDir = $this->_remoteStorageManager->getRemoteDirectoryRelativePath();
		$tarFileId = $this->_dao->registerTarFile($tarFile, $remoteDir);
		$this->_setTarFile($bucketPath, $tarFileId, true);
		$this->_logger->addDebug("Registered tar file with media database (id=$tarFileId;file=\"$tarFile\")");
	}


	private function _doPHPTar($bucket)
	{
		$bucketPath = FileUtil::createPath($this->_bucketsDir, $bucket);
		if(FileUtil::isEmptyDir($bucketPath)) {
			$this->_logger->addWarning("Ignoring empty bucket: $bucketPath");
			return;
		}
		$tarFile = $this->_getTarFileName($bucket);
		$tarPath = FileUtil::createPath($this->_tarsDir, $tarFile);
		$this->_logger->addDebug("Bucket: $bucketPath");
		$this->_logger->addDebug("Tar file: $tarPath");
		$tar = new \PharData($tarPath, \RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
		$tar->buildFromDirectory($bucketPath);
		$remoteDir = $this->_remoteStorageManager->getRemoteDirectoryRelativePath();
		$tarFileId = $this->_dao->registerTarFile($tarFile, $remoteDir);
		if($this->_config->getBoolean('offload.immediate')) {
			$this->_remoteStorageManager->send($tarFile);
			if(!$this->_remoteStorageManager->hasArrived($tarFile)) {
				throw new Exception('Tar file did not arrive on remote server: ' . $tarFile);
			}
			$this->_setTarFile($bucketPath, $tarFileId, true);
		}
		else {
			// Offloading will be carried out by RemoteStorageManager.
			// We only bootstrap a record in the tar_file table here.
			$this->_setTarFile($bucketPath, $tarFileId, false);
		}
	}
	
	// For each file in the specified bucket set the tar_file_id
	// column to the specified tar file id, and set the backup_ok
	// column according to the $backupComplete argument.
	private function _setTarFile($bucket, $tarFileId, $backupComplete)
	{
		foreach(FileUtil::scandir($bucket) as $file) {
			if($file === '.' || $file === '..') {
				continue;
			}
			$dashPos = strpos($file, '-');
			if($dashPos === false) {
				throw new \Exception("Could not extract database id from file $file: missing hyphen (\"-\") in file name");
			}
			$mediaId = (int) substr($file, 0, $dashPos);
			if($this->_dao->setTarFile($mediaId, $tarFileId, $backupComplete) === false) {
				$this->_logger->addDebug("No record found for \"$file\". Ignored.");
			}
		}
	}


	private function _getTarFileName($bucket)
	{
		$backupGroup = str_pad($this->_backupGroup, 3, '0', STR_PAD_LEFT);
		$absoluteStart = $this->_context->getRequiredProperty('start');
		return sprintf('%s_%s_%s.tar', date('YmdHis', $absoluteStart), $bucket, $backupGroup);
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on creating tar files: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}
