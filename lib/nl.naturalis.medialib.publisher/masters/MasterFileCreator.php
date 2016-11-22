<?php

namespace nl\naturalis\medialib\publisher\masters;

use \Exception;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\exception\UserInterruptException;
use nl\naturalis\medialib\publisher\db\dao\MasterPublisherDAO;
use nl\naturalis\medialib\publisher\exception\StaleRecordException;
use nl\naturalis\medialib\publisher\exception\ImageMagickException;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\Command;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\context\Context;
use Monolog\Logger;

class MasterFileCreator {
	// media file must just be moved to the master directory
	const ACTION_MOVE = 1;
	// image must be convert to jpeg, bus is already smaller
	// than the maximum size for a master image
	const ACTION_CONVERT = 2;
	// image must be resized so it fits within the maximum
	// size for a master image
	const ACTION_RESIZE = 3;
	
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
	 * @var MasterPublisherDAO
	 */
	private $_dao;
	/**
	 *
	 * @var int Running total of processed media files
	 */
	private $_numProcessed;
	/**
	 *
	 * @var int The running total of image processing errors.
	 */
	private $_numErrors;
	
	/**
	 * Textual representation of today's date.
	 *
	 * @var string
	 */
	private $_today;


	public function __construct(Context $context)
	{
		$this->_context = $context;
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new MasterPublisherDAO($context);
		$this->_today = date('Ymd', $context->getRequiredProperty('start'));
	}


	public function createMasterFile($media)
	{
		$root = $this->_context->getConfig()->masterDirectory;
		$targetDir = FileUtil::mkdir($root, $this->_context->getConfig()->producer, false);
		$targetDir = FileUtil::mkdir($targetDir, $this->_today, false);
		$this->processFile($media, $targetDir);
		// Contrary to createMasterFiles(), used for batch-wise
		// processing, we deliberately do not handle any exception
		// thrown from processFile(). If an exception is thrown,
		// it's entirely up to the caller to deal with it.
	}


	public function createMasterFiles()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		$this->_numProcessed = 0;
		$this->_numErrors = 0;
		
		try {
			
			$this->_logger->addInfo('Publishing master files');
			
			$root = $this->_context->getConfig()->masterDirectory;
			$producer = $this->_context->getConfig()->producer;
			$targetDir = FileUtil::mkdir($root, $producer, false);
			$targetDir = FileUtil::mkdir($targetDir, $this->_today, false);
			
			$this->_logger->addDebug('Searching for unprocessed media from producer ' . $producer);
			$records = $this->_dao->getUnprocessedMedia($producer);
			$this->_logger->addDebug('Query complete. Found ' . count($records) . ' unprocessed media');
			
			foreach($records as $media) {
				PublisherObject::checkPanicFile($panicFile);
				if(++$this->_numProcessed % 100 === 0) {
					$this->_logger->addDebug("Files processed: {$this->_numProcessed}");
				}
				try {
					$this->processFile($media, $targetDir);
				}
				catch(StaleRecordException $e) {
					$this->_logger->addWarning($e->getMessage());
				}
				catch(ImageMagickException $e) {
					$this->_logger->addError($e->getMessage());
					$this->_logger->addDebug('Command: ' . $e->getCommand()->getCommandLine());
					if(++$this->_numErrors === ((int) $this->_context->getConfig()->imagemagick->maxErrors)) {
						throw new Exception('Maximum number of image processing errors reached');
					}
				}
			}
		}
		catch(Exception $e) {
			$this->_logStatistics($startTime);
			throw $e;
		}
		
		$this->_logStatistics($startTime);
	}


	/**
	 * Get number of media files processed in most recent call to {@link #createMasterFiles()}.
	 */
	public function getNumProcessed()
	{
		return $this->_numProcessed;
	}


	/**
	 * Get number of imagemagick processing errors in most recent call to {@link #createMasterFiles()}.
	 */
	public function getNumErrors()
	{
		return $this->_numErrors;
	}


	/**
	 * Process one media file. If it is an image, resize and/or convert it
	 * when necessary. Then copy the file to a directory outside the staging
	 * area (which will be deleted sooner or later). To be more precise: the
	 * file is moved to a special directory structure for master files, which
	 * will be access later on by the {@code WebPublisher}. Although you can
	 * call this method to create a single master file, it is more convenient
	 * to call {@link #createMasterFile()} for this purpose, as this method
	 * will calculate and, if necessry, create the target directory and error
	 * directory for you - and then call processFile().
	 * 
	 * @param stdClass $media The database record, converted to an anonymous object, for the media file.
	 * @param string $targetDir The full path to the directory to which to move the processed file
	 * @param string $errorDir The name (NOT the full path!) of the directory to which to move the file
	 * in case ImageMagick threw an error. 
	 * 
	 * @throws ImageMagickException
	 * @throws StaleRecordException
	 */
	public function processFile($media, $targetDir)
	{
		$path = $media->source_file;
		if(!is_file($path)) {
			$this->_dao->deleteMedia($media->id);
			throw new StaleRecordException($media, StaleRecordException::SOURCE_FILE);
		}
		$action = $this->_getAction($path);
		if($action === self::ACTION_MOVE) {
			$masterFile = $targetDir . DIRECTORY_SEPARATOR . basename($path);
			if(is_file($masterFile)) {
				FileUtil::unlink($masterFile);
			}
			FileUtil::copy($path, $masterFile);
			$this->_dao->setMasterFile($media->id, $masterFile);
		}
		else {
			$newFile = FileUtil::basename($path) . '.jpg';
			$masterFile = $targetDir . DIRECTORY_SEPARATOR . $newFile;
			try {
				$this->_doImageMagick($path, $masterFile, $action);
				$this->_dao->setMasterFile($media->id, $masterFile);
			}
			catch(ImageMagickException $e) {
				$this->_moveToCemetary($media);
				throw $e;
			}
		}
	}


	private function _doImageMagick($inputFile, $outputFile, $action)
	{
		// Overwrite file in output directory
		if(is_file($outputFile)) {
			FileUtil::unlink($outputFile);
		}
		$convert = $this->_context->getConfig()->imagemagick->convertCommand;
		$resize = $this->_context->getConfig()->imagemagick->resizeCommand;
		$format = $action === self::ACTION_RESIZE ? $resize : $convert;
		$commandLine = sprintf($format, $inputFile, $outputFile);
		$command = new Command($commandLine);
		if($command->execute() != 0) {
			throw new ImageMagickException($inputFile, $command);
		}
	}
	

	// File types to consider for being resized by ImageMagick
	private $_fileTypes = null;


	private function _getAction($inputFile)
	{
		if($this->_fileTypes === null) {
			$this->_fileTypes = explode(',', strtolower($this->_context->getConfig()->resizeWhen->fileType));
		}
		$ext = FileUtil::getExtension($inputFile, true);
		if(!in_array($ext, $this->_fileTypes)) {
			// We are dealing with a non-image file; just move
			// it to its final destination (the directory accessed
			// by the media server).
			return self::ACTION_MOVE;
		}
		// Maximum width and height of images
		$max = (int) $this->_context->getConfig()->resizeWhen->imageSize;
		$info = getimagesize($inputFile);
		$w = $info[0];
		$h = $info[1];
		if($w > $max || $h > $max) {
			return self::ACTION_RESIZE;
		}
		if($ext === 'jpg' || $ext === 'jpeg') {
			// The image is within the maximum bounds and it
			// also already is a jpeg image; just move it to
			// the target directory
			return self::ACTION_MOVE;
		}
		// The image is within the maximum bounds, but it
		// is not a jpeg image; convert it to jpeg.
		return self::ACTION_CONVERT;
	}


	/**
	 * Handle corrupt media files. Delete corresponding record from
	 * media database so it won't get processed over and over again;
	 * chop off the database id from the file name, which was prefixed
	 * by the {@code MediaIndexer}; move the file to a subdirectory
	 * of the "deadImagesDirectory".
	 * 
	 * @param object $media The record from the media table corresponding
	 * to the corrupt media file, fetched using {@code PDO::FETCH_OBJ}.
	 */
	private function _moveToCemetary($media)
	{
		$this->_dao->deleteMedia($media->id);
		$cemetary = $this->_context->getConfig()->deadImagesDirectory;
		$cemetary = FileUtil::mkdir($cemetary, 'source_files', false);
		$cemetary = FileUtil::mkdir($cemetary, $this->_context->getConfig()->producer, false);
		$cemetary = FileUtil::mkdir($cemetary, $this->_today, false);
		$fileName = basename($media->source_file);
		// Restore original file name, that is, the file name without
		// the database ID prefix (JIRA issue BB-56).
		$fileName = substr($fileName, strpos($fileName, '-') + 1);
		$target = $cemetary . DIRECTORY_SEPARATOR . $fileName;
		FileUtil::rename($media->source_file, $target);
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Files processed: ' . $this->_numProcessed);
		$this->_logger->addInfo('Image processing errors: ' . $this->_numErrors);
		$this->_logger->addInfo('Time spent on creating master files: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}