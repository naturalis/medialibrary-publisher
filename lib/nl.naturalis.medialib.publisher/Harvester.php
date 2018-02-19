<?php

namespace nl\naturalis\medialib\publisher;

use \Exception;
use nl\naturalis\medialib\publisher\common\ConfigChecker;
use nl\naturalis\medialib\publisher\harvest\StagingAreaManager;
use nl\naturalis\medialib\publisher\harvest\MediaFileIndexer;
use nl\naturalis\medialib\util\DateTimeUtil;

/**
 * Class that manages the harvesting process.
 *
 * @author ayco_holleman
 */
class Harvester extends PublisherObject {
	private $_succes;
	private $_numNewFiles;
	private $_numResubmits;
	private $_numErrors;
	private $_totalSize;


	public function __construct($iniFile)
	{
		parent::__construct($iniFile);
	}


	protected function _getDiscriminatorName()
	{
		return "producer";
	}


	protected function _getDiscriminatorValue()
	{
		return $this->_context->getConfig()->producer;
	}


	protected function _execute()
	{
		$start = time();
		
		// Allow the StagingAreaManager, MediaFileIndexer, TarAreaCreator,
		// TarFileCreator and OfflineStorageManager to look up the absolute
		// start time of the harvesting process, should they need it.
		$this->_context->setProperty('start', $start);
		
		$this->_success = true;
		$this->_numProcessed = 0;
		$this->_numNewFiles = 0;
		$this->_numResubmits = 0;
		$this->_numErrors = 0;
		$this->_totalSize = 0;
		
		try {
			
			$configChecker = new ConfigChecker($this->_context);
			$configChecker->checkConfig();
			
			$stagingAreaManager = new StagingAreaManager($this->_context);
			$stagingAreaManager->createStagingArea();
			
			$mediaFileIndexer = new MediaFileIndexer($this->_context);
			$mediaFileIndexer->setPhase1Directory($stagingAreaManager->getPhase1Directory());
			$mediaFileIndexer->setPhase2Directory($stagingAreaManager->getPhase2Directory());

			
			// Process files from the "resubmit" directory. Files placed
			// in this directory must be regarded as revisions or fixes of
			// previously indexed media files. They must overwrite these
			// files, both on the file system and in the database. They
			// must never be regarded (and rejected) as duplicates.
			$this->_logger->addInfo('Processing resubmitted media');
			$stagingAreaManager->moveMediaToStagingArea($this->_context->getConfig()->resubmitDirectory);
			$mediaFileIndexer->indexMediaFiles(true);
			$this->_numProcessed = $mediaFileIndexer->getNumProcessed();
			$this->_numResubmits = $mediaFileIndexer->getNumIndexed();
			$this->_numErrors = $mediaFileIndexer->getNumErrors();
			$this->_totalSize = $mediaFileIndexer->getTotalFileSize();
			
			// Then from the regular "harvest" directory. Files placed in
			// this regarded must be presumed to be new. Therefore, if
			// the database already has a record for this file name, the
			// new file will be rejected as a duplicate and placed into the
			// "duplicates" directory.
			$this->_logger->addInfo('Processing new media');
			$stagingAreaManager->moveMediaToStagingArea($this->_context->getConfig()->harvestDirectory);
			$mediaFileIndexer->indexMediaFiles(false);
			$this->_numProcessed += $mediaFileIndexer->getNumProcessed();
			$this->_numNewFiles = $mediaFileIndexer->getNumIndexed();
			$this->_numErrors += $mediaFileIndexer->getNumErrors();
			$this->_totalSize += $mediaFileIndexer->getTotalFileSize();
			$this->_didWork = ($this->_numProcessed !== 0);
		}
		catch(Exception $e) {
			$this->_success = false;
			$this->_logger->addInfo('Total harvest time: ' . DateTimeUtil::hoursMinutesSeconds((time() - $start), true));
			throw $e;
		}
		
		$this->_logger->addInfo('Total harvest time: ' . DateTimeUtil::hoursMinutesSeconds((time() - $start), true));
	}


	protected function _getEmailSubjectLine()
	{
		if(!$this->_success) {
			return 'FOUT: Verwerking van bestanden in harvest of resubmit map onverwacht afgebroken.';
		}
		if($this->_numErrors !== 0) {
			return "PAS OP: {$this->_numErrors} fouten tijdens het verwerken van de harvest en resubmit map!";
		}
		return "SUCCES: {$this->_numNewFiles} nieuwe media bestanden verwerkt; {$this->_numResubmits} opnieuw aangeboden bestanden verwerkt";
	}

}
