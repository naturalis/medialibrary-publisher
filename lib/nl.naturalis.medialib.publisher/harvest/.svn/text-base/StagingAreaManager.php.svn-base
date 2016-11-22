<?php

namespace nl\naturalis\medialib\publisher\harvest;

use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\publisher\db\dao\HarvesterDAO;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\Config;
use Monolog\Logger;

class StagingAreaManager {
	// Subdirectory containing new arrivals
	const PHASE1_SUBDIR = 'phase1';
	// Subdirectory containing indexed media files
	const PHASE2_SUBDIR = 'phase2';
	
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
	 * @var string
	 */
	private $_stageDir;
	
	/**
	 *
	 * @var string
	 */
	private $_phase1Dir;
	/**
	 *
	 * @var string
	 */
	private $_phase2Dir;


	public function __construct(Context $context)
	{
		$this->_context = $context;
		$this->_logger = $context->getLogger(__CLASS__);
	}


	/**
	 * The directory used as staging area for the harvesting process for a
	 * particular digistraat. Under this directory a "phase1", a "phase2", a
	 * "buckets" and a "tars" directory will be created.
	 *
	 * @return string
	 */
	public function createStagingArea()
	{
		$dao = new HarvesterDAO($this->_context);
		$dao->checkDatabase();
		$time = $this->_context->getRequiredProperty('start');
		$root = $this->_context->getConfig()->stagingDirectory;
		$producer = $this->_context->getConfig()->producer;
		$dir = FileUtil::mkdir($root, date('YmdHis', $time), false);
		$this->_stageDir = FileUtil::mkdir($dir, $producer, false);
		$this->_phase1Dir = FileUtil::mkdir($this->_stageDir, self::PHASE1_SUBDIR, false);
		$this->_phase2Dir = FileUtil::mkdir($this->_stageDir, self::PHASE2_SUBDIR, false);
	}


	/**
	 * Create "phase1" subdirectory in the staging area, if it does not exist
	 * already.
	 */
	public function getPhase1Directory()
	{
		return $this->_phase1Dir;
	}


	/**
	 * <p>
	 * Create "phase1" subdirectory in the staging area, if it does not exist
	 * already.
	 * </p>
	 * <p>
	 * Files from the harvest directory will first be moved to the phase1
	 * directory. Then the MediaFileIndexer comes around, iterates over the
	 * files in the phase1 directory, prefixes them with a database ID, and
	 * moves them to the phase2 directory. Originally the renamed files were
	 * not moved to another directory. However, this causes the
	 * {@code RecursiveDirectoryIterator} to process the same file twice.
	 * That's why we have a phase1 and a phase2 directory.
	 * </p>
	 */
	public function getPhase2Directory()
	{
		return $this->_phase2Dir;
	}


	/**
	 * Move the files from the harvest or resubmit directory to the phase1
	 * directory.
	 */
	public function moveMediaToStagingArea($sourceDir)
	{		
		$this->_logger->addInfo('Moving files to staging area');
		$start = time();	
		if(!FileUtil::isEmptyDir($this->_phase1Dir)) {
			throw new \Exception("Directory {$this->_phase1Dir} must be empty before staging media files");
		}
		foreach(FileUtil::scandir($sourceDir) as $file) {
			if($file === "." || $file === "..") {
				continue;
			}
			$source = $sourceDir . DIRECTORY_SEPARATOR . $file;
			$target = $this->_phase1Dir . DIRECTORY_SEPARATOR . $file;
			FileUtil::rename($source, $target);
		}
		$seconds = time() - $start;
		$this->_logger->addInfo('Time spent on populating staging area: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}