<?php

namespace nl\naturalis\medialib\publisher\harvest;

use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\db\dao\HarvesterDAO;
use nl\naturalis\medialib\publisher\exception\DuplicateMediaFileException;
use nl\naturalis\medialib\publisher\exception\FileNameTooLongException;
use nl\naturalis\medialib\publisher\exception\MediaNotFoundException;
use nl\naturalis\medialib\publisher\exception\UserInterruptException;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\Spinner;
use Monolog\Logger;

/**
 * The {@code MediaFileIndexer} is responsible for registering media files
 * with the database. The {@code MediaFileIndexer} is run by the {@link Harvester}
 * after the {@link StagingAreaManager} has created the various directories
 * populated and read during the backup and publishing process. Once a media file
 * is indexed it is ready for two things: [1] it can be backed up (i.e. sent over
 * via FTP to Sound & Vision); [2] it can be picked up by the {@link MasterPublisher},
 * which will extract a master file from it.
 *
 * @author ayco_holleman
 */
class MediaFileIndexer {
	const MAX_REGNO_LENGTH = 48;
	
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
	 * @var string The directory to which the StagingAreaManager has moved the
	 *      media files that it found in the harvest directory.
	 */
	private $_phase1Dir;
	
	/**
	 *
	 * @var string The directory to which the MediaFileIndexer will move the
	 *      media files after having registered them with the media database.
	 */
	private $_phase2Dir;
	private $_numProcessed = 0;
	
	/**
	 * Number of successfully indexed media files
	 * @var int
	 */
	private $_numIndexed = 0;
	/**
	 * Number of duplicates in the harvest directory,
	 * or non-duplicates in the resubmit directory
	 * @var int
	 */
	private $_numErrors = 0;
	/**
	 * Total file size of indexed files
	 * @var int
	 */
	private $_totalSize = 0;


	public function __construct(Context $context)
	{
		$this->_context = $context;
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new HarvesterDAO($context);
	}


	public function setPhase1Directory($dir)
	{
		$this->_phase1Dir = $dir;
	}


	public function setPhase2Directory($dir)
	{
		$this->_phase2Dir = $dir;
	}


	/**
	 * Iterate over the "phase1" directory (see {@link StagingAreaManager}), register
	 * the media files in it with the database, and move them over to the "phase2"
	 * directory. Note that while the phase1 directory may have a nested directory
	 * structure, the media files will always end up directly under the phase2
	 * directory. This will never cause name clashes because by that time, the database
	 * id for the corresponding record will have been prefixed to the original file
	 * name.
	 */
	public function indexMediaFiles($isResubmit = false)
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		$this->_numProcessed = 0;
		$this->_numIndexed = 0;
		$this->_numErrors = 0;
		$this->_totalSize = 0;
		
		try {
			
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->_phase1Dir));
			$fileTypes = $this->_getFileTypes();
			$spinner = new Spinner($this->_config->numBackupGroups);
			
			while($iterator->valid()) {
				PublisherObject::checkPanicFile($panicFile);
				$path = $iterator->key();
				if(is_file($path) && in_array(FileUtil::getExtension($path), $fileTypes)) {
					try {
						$this->index($path, $spinner->next(), $isResubmit);
					}
					catch(FileNameTooLongException $e) {
						$this->_logger->addError($e->getMessage());
						$newPath = FileUtil::createPath($this->_config->deadImagesDirectory, basename($path));
						FileUtil::rename($path, $newPath);
					}
					catch(MediaNotFoundException $e) {
						$this->_logger->addError($e->getMessage());
						$this->_moveToCemetary($path);
					}
					catch(DuplicateMediaFileException $e) {
						$this->_logger->addError($e->getMessage());
						$newPath = FileUtil::createPath($this->_config->duplicatesDirectory, basename($path));
						FileUtil::rename($path, $newPath);
					}
				}
				$iterator->next();
			}
		}
		catch(Exception $e) {
			$this->_logStatistics($startTime);
			throw $e;
		}
		
		$this->_logStatistics($startTime);
	}


	public function index($path, $backupGroup, $isResubmit = false)
	{
		++$this->_numProcessed;
		$file = basename($path);
		$regno = FileUtil::basename($file);
		if(strlen($regno) > self::MAX_REGNO_LENGTH) {
			throw new FileNameTooLongException($regno, self::MAX_REGNO_LENGTH);
		}
		$mediaId = $this->_dao->getMediaId($regno);
		if($mediaId === false) {
			if($isResubmit) {
				++$this->_numErrors;
				throw new MediaNotFoundException($path);
			}
			// Create an empty record for the media file
			$mediaId = $this->_dao->newMediaFile($regno);
		}
		else {
			if(!$isResubmit) {
				++$this->_numErrors;
				throw new DuplicateMediaFileException($path);
			}
		}
		$fileSize = filesize($path);
		$this->_totalSize += $fileSize;
		$mediaIdString = str_pad($mediaId, 9, '0', STR_PAD_LEFT);
		// Prepend database id to file name and move file to phase2 directory,
		// where it will be picked up by the MasterPublisher and the Offloader
		$newPath = $this->_phase2Dir . DIRECTORY_SEPARATOR . $mediaIdString . '-' . $file;
		FileUtil::rename($path, $newPath);
		$this->_dao->resetStatus($mediaId, $this->_config->producer, $this->_config->owner, $newPath, $fileSize, $backupGroup);
		++$this->_numIndexed;
		return $mediaId;
	}


	public function getNumProcessed()
	{
		return $this->_numProcessed;
	}


	public function getNumIndexed()
	{
		return $this->_numIndexed;
	}


	public function getNumErrors()
	{
		return $this->_numErrors;
	}


	public function getTotalFileSize()
	{
		return $this->_totalSize;
	}


	private function _moveToCemetary($path)
	{
		$today = date('Ymd', $this->_context->getRequiredProperty('start'));
		$cemetary = $this->_config->deadImagesDirectory;
		$cemetary = FileUtil::mkdir($cemetary, 'resubmits', false);
		$cemetary = FileUtil::mkdir($cemetary, $this->_config->producer, false);
		$cemetary = FileUtil::mkdir($cemetary, $today, false);
		$fileName = basename($path);
		$target = $cemetary . DIRECTORY_SEPARATOR . $fileName;
		FileUtil::rename($path, $target);
	}


	private function _getFileTypes()
	{
		$fileTypes = $this->_config->fileTypes;
		if($fileTypes === null) {
			$fileTypes = 'tiff,tif';
		}
		$fileTypesArray = explode(',', $fileTypes);
		for($i = 0; $i < count($fileTypesArray); ++$i) {
			$fileTypesArray[$i] = strtolower(trim($fileTypesArray[$i]));
		}
		return $fileTypesArray;
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Files processed: ' . $this->_numProcessed);
		$this->_logger->addInfo("Successfully indexed files: {$this->_numIndexed}");
		if($this->_numErrors === 0) {
			$this->_logger->addInfo("Rejected files: 0");
		}
		else {
			$this->_logger->addWarning("Rejected files: {$this->_numErrors}");
		}
		$this->_logger->addInfo(sprintf('Total size of indexed media files: %01.2f MB', ($this->_totalSize / (1024 * 1024))));
		$this->_logger->addInfo('Time spent on indexing media: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}