<?php

namespace nl\naturalis\medialib\publisher;

use \Exception;
use nl\naturalis\medialib\publisher\clean\StagingAreaCleaner;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\publisher\clean\TarAreaCleaner;

/**
 * Driver class for the file system cleanup process.
 * 
 * @author ayco_holleman
 *
 */
class Cleaner extends PublisherObject {
	private $_succes;
	private $_clean;


	public function __construct($iniFile)
	{
		parent::__construct($iniFile);
	}


	protected function _getDiscriminatorName()
	{
		if($this->_context->getConfig()->getBoolean('cleaner.sweep')) {
			return 'staging directory';
		}
		return 'producer';
	}


	protected function _getDiscriminatorValue()
	{
		if($this->_context->getConfig()->getBoolean('cleaner.sweep')) {
			return md5($this->_context->getConfig()->stagingDirectory);
			//return preg_replace('/[^a-zA-Z0-9._]/', '-', $this->_context->getConfig()->stagingDirectory);
		}
		return $this->_context->getConfig()->producer;
	}


	protected function _execute()
	{
		$start = time();
		$this->_success = true;
		$this->_clean = true;		
		try {
			// We need to clean up the tar area first, because the files
			// in the buckets are actually symlinks to files in the "phase2"
			// directory
			$tarAreaCleaner = new TarAreaCleaner($this->_context);
			$this->_clean = $tarAreaCleaner->cleanup();
			$stagingAreaCleaner = new StagingAreaCleaner($this->_context);
			if(!$stagingAreaCleaner->cleanup()) {
				$clean = false;
			}
		}
		catch(Exception $e) {
			$this->_success = false;
			throw $e;
		}
		$this->_logger->addInfo('Total cleanup time: ' . DateTimeUtil::hoursMinutesSeconds((time() - $start), true));
	}


	protected function _getEmailSubjectLine()
	{
		if($this->_success) {
			if($this->_clean) {
				return 'SUCCES: Alle oude bestanden verwijderd';
			}
			else {
				return 'PAS OP: Niet alle oude bestanden zijn verwijderd';
			}
		}
		else {
			return 'FOUT: Opruimen oude bestanden overwacht afgebroken';
		}
	}

}
