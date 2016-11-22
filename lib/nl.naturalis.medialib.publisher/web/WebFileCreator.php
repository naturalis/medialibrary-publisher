<?php

namespace nl\naturalis\medialib\publisher\web;

use \Exception;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\db\dao\WebPublisherDAO;
use nl\naturalis\medialib\publisher\exception\StaleRecordException;
use nl\naturalis\medialib\publisher\exception\ImageMagickException;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\Command;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\Config;
use Monolog\Logger;


/**
 * Driver class for the process that creates/publishes the web resources.
 * 
 * @author ayco_holleman
 */
class WebFileCreator {
	const ACTION_MOVE = 1;
	const ACTION_DERIVE = 2;
	
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
	 * @var WebPublisherDAO
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
		$this->_dao = new WebPublisherDAO($context);
		$this->_today = date('Ymd', $context->getRequiredProperty('start'));
	}


	public function createWebFiles()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		$this->_numProcessed = 0;
		$this->_numErrors = 0;
		
		try {
			
			$this->_logger->addInfo('Publishing media to web');
			
			$this->_logger->addDebug('Searching for unprocessed media from producer ' . $this->_context->getConfig()->producer);
			$records = $this->_dao->getUnprocessedMedia($this->_context->getConfig()->producer);
			$this->_logger->addDebug('Query complete. Found ' . count($records) . ' unprocessed media');
			
			foreach($records as $media) {
				PublisherObject::checkPanicFile($panicFile);
				if(++$this->_numProcessed % 100 === 0) {
					$this->_logger->addDebug("Files processed: {$this->_numProcessed}");
				}
				try {
					$this->createWebFile($media);
				}
				catch(StaleRecordException $e) {
					$this->_logger->addWarning($e->getMessage());
				}
				catch(ImageMagickException $e) {
					$this->_logger->addError($e->getMessage());
					$this->_logger->addDebug('Command: ' . $e->getCommand()->getCommandLine());
					if(++$this->_numErrors === ((int) $this->_context->getConfig()->imagemagick->maxErrors)) {
						throw new \Exception('Maximum number of image processing errors reached');
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
	 * Process a single media file. Derive small-, medium- and large-sized image from
	 * it, if it is an image file. And move the file(s) to a directory that is accessible
	 * to the media server.
	 * 
	 * @param stdClass $media A record from the media table, converted to an anonymous object.
	 * 
	 * @throws StaleRecordException
	 * @throws ImageMagickException
	 */
	public function createWebFile($media)
	{
		$path = $media->master_file;
		if(!is_file($path)) {
			$this->_dao->deleteMedia($media->id);
			throw new StaleRecordException($media, StaleRecordException::MASTER_FILE);
		}
		
		$wwwDir = $this->_context->getConfig()->wwwDirectory;
		$wwwDir = FileUtil::mkdir($wwwDir, $this->_context->getConfig()->producer, false);
		$wwwDir = FileUtil::mkdir($wwwDir, $this->_today, false);
		
		$largeDir = FileUtil::mkdir($wwwDir, 'large', false);
		$mediumDir = FileUtil::mkdir($wwwDir, 'medium', false);
		$smallDir = FileUtil::mkdir($wwwDir, 'small', false);
		
		$action = $this->_getAction($path);
		$wwwFile = basename($path);
		
		if($action === self::ACTION_MOVE) {
			$target = $wwwDir . DIRECTORY_SEPARATOR . $wwwFile;
			if(is_file($target)) {
				FileUtil::unlink($target);
			}
			FileUtil::copy($path, $target);
		}
		else {
			try {
				$this->_doImageMagick($path, array(
						$largeDir,
						$mediumDir,
						$smallDir
				));
			}
			catch(ImageMagickException $e) {
				$this->_moveToCemetary($media);
				throw $e;
			}
		}
		
		$this->_dao->setDirectoryAndFileName($media->id, $wwwDir, $wwwFile);
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


	private function _doImageMagick($inputFile, $imageDirs)
	{
		$wwwFile = basename($inputFile);
		$wwwFileLarge = $imageDirs[0] . DIRECTORY_SEPARATOR . $wwwFile;
		$wwwFileMedium = $imageDirs[1] . DIRECTORY_SEPARATOR . $wwwFile;
		$wwwFileSmall = $imageDirs[2] . DIRECTORY_SEPARATOR . $wwwFile;
		
		FileUtil::unlink($wwwFileLarge, true);
		FileUtil::unlink($wwwFileMedium, true);
		FileUtil::unlink($wwwFileSmall, true);
		
		$cfg = $this->_context->getConfig()->imagemagick;
		
		$commandLine = "{$cfg->command} \"{$inputFile}\"";
		
		$commandLine .= " -resize {$cfg->large->size}x{$cfg->large->size} -quality {$cfg->large->quality} -write \"{$wwwFileLarge}\"";
		$commandLine .= " -resize {$cfg->medium->size}x{$cfg->medium->size} -quality {$cfg->medium->quality} -write \"{$wwwFileMedium}\"";
		$commandLine .= " -resize {$cfg->small->size}x{$cfg->small->size} -quality {$cfg->small->quality} \"{$wwwFileSmall}\"";
		
		$command = new Command($commandLine);
		if($command->execute() != 0) {
			throw new ImageMagickException($inputFile, $command);
		}
	}


	private function _getAction($inputFile)
	{
		$ext = FileUtil::getExtension($inputFile, true);
		if($ext === 'jpg' || $ext === 'jpeg') {
			// This is an image file; create derived images
			// (thumbnail, medium, large)
			return self::ACTION_DERIVE;
		}
		// This is a non-image file; just move it to the www
		// directory
		return self::ACTION_MOVE;
	}


	private function _updateMediaDb($baseDirId, $subdir, $file)
	{
		$dashPos = strpos($file, '-');
		if($dashPos === false) {
			throw new \Exception("Could not extract database id from file $file: missing hyphen (\"-\") in file name");
		}
		$id = (int) substr($file, 0, $dashPos);
		$this->_dao->setPathInfo($id, $baseDirId, $subdir, $file);
	}


	private function _moveToCemetary($media)
	{
		$this->_dao->deleteMedia($media->id);
		$cemetary = $this->_context->getConfig()->deadImagesDirectory;
		$cemetary = FileUtil::mkdir($cemetary, 'master_files', false);
		$cemetary = FileUtil::mkdir($cemetary, $this->_context->getConfig()->producer, false);
		$cemetary = FileUtil::mkdir($cemetary, $this->_today, false);
		$fileName = basename($media->master_file);
		// Restore original file name, that is, the file name without
		// the database ID prefix (JIRA issue BB-56).
		$fileName = substr($fileName, strpos($fileName, '-') + 1);
		$target = $cemetary . DIRECTORY_SEPARATOR . $fileName;
		FileUtil::rename($media->master_file, $target);
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Files processed: ' . $this->_numProcessed);
		$this->_logger->addInfo('Image processing errors: ' . $this->_numErrors);
		$this->_logger->addInfo('Time spent on creating web resources: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}