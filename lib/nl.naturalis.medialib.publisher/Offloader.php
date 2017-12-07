<?php

namespace nl\naturalis\medialib\publisher;

use \Exception;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\offload\TarAreaManager;
use nl\naturalis\medialib\publisher\offload\TarFileCreator;
use nl\naturalis\medialib\publisher\offload\RemoteStorageManager;
use nl\naturalis\medialib\publisher\exception\JoblessException;
use nl\naturalis\medialib\util\DateTimeUtil;


/**
 * The driver class for the backup process. Sequences and drives the objects in the offload folder.
 *
 * @author ayco_holleman
 */
class Offloader extends PublisherObject {
	private $_backupGroup;
	private $_success;


	public function __construct($iniFile, $backupGroup)
	{
		parent::__construct($iniFile);
		$this->_backupGroup = (int) $backupGroup;
	}


	protected function _getDiscriminatorName()
	{
		return "backup group";
	}


	protected function _getDiscriminatorValue()
	{
		return $this->_backupGroup;
	}


	protected function _execute()
	{
		$start = time();
		$this->_success = true;
		// Allow objects instantiated by the offloader to look up the
		// absolute start time of the harvesting process, should they
		// need it.
		$this->_context->setProperty('start', $start);
		
		try {
			
			$tarAreaManager = new TarAreaManager($this->_context, $this->_backupGroup);
			$tarAreaManager->createTarArea();
			// Ruud: changed behaviour: moveMediaToBuckets returns list of files instead
			// of number of files. This allows us to reuse the files' ids in the database.
			// If not set, we must query the entire database for the file's id...
			$fileList = $tarAreaManager->moveMediaToBuckets();
			$numMedia = count($fileList);
			
			if($numMedia == 0) {
				throw new JoblessException();
			}
			
			$remoteStorageManager = new AwsStorageManager($this->_context);
			$remoteStorageManager->setTarsDirectory($tarAreaManager->getTarsDirectory());
			$remoteStorageManager->setFileList($fileList);
			$remoteStorageManager->sendBatch();
			
			/* FTP method

			$tarFileCreator = new TarFileCreator($this->_context, $this->_backupGroup);
			$tarFileCreator->setBucketsDirectory($tarAreaManager->getBucketsDirectory());
			$tarFileCreator->setTarsDirectory($tarAreaManager->getTarsDirectory());
			$tarFileCreator->setBackupGroup($this->_backupGroup);
			$tarFileCreator->setRemoteStorageManager($remoteStorageManager);
			$tarFileCreator->createTarFiles();
			
			
			// If tar files must be offloaded right after they are created, the 
			// {@code TarFileCreator} will call the {@code RemoteStorageManager}'s
			// send() method for each tar file it creates. Otherwise it is left to
			// the {@code RemoteStorageManager} to offload all the tar files using
			// its sendBatch() method. When the offload method is CUSTOM, the
			// {@code RemoteStorageManager} remains completely idle because with
			// this method tarring and offloading is done atomically using one
			// custom command or script.
			$offloadImmediate = $this->_context->getConfig()->getBoolean('offload.immediate');
			$offloadMethod = $this->_context->getConfig()->offload->method;
			if(!$offloadImmediate && $offloadMethod !== 'CUSTOM') {
				$remoteStorageManager->sendBatch();
			}
			
			*/
		}
		catch(Exception $e) {
			if(!($e instanceof JoblessException)) {
				$this->_success = false;
			}
			$this->_logger->addInfo('Total offload time: ' . DateTimeUtil::hoursMinutesSeconds((time() - $start), true));
			throw $e;
		}
		
		$this->_logger->addInfo('Total offload time: ' . DateTimeUtil::hoursMinutesSeconds((time() - $start), true));
	}


	protected function _getEmailSubjectLine()
	{
		if($this->_success) {
			return 'SUCCES: Bestanden doorgestuurd naar B&G';
		}
		return 'FOUT: Backup onverwacht afgebroken';
	}

}