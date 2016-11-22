<?php

namespace nl\naturalis\medialib\publisher;

use \Exception;
use nl\naturalis\medialib\publisher\masters\MasterFileCreator;
use nl\naturalis\medialib\util\EmailUtil;


/**
 * Driver class for the process that creates/publishes the master files.
 * 
 * @author ayco_holleman
 */
class MasterPublisher extends PublisherObject {
	private $_success;
	private $_numErrors;


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
		$this->_success = true;
		// Allow objects instantiated by the offloader to look up the
		// absolute start time of the harvesting process, should they
		// need it.
		$this->_context->setProperty('start', $start);
		$masterFileCreator = new MasterFileCreator($this->_context);
		try {
			$masterFileCreator->createMasterFiles();
			$this->_didWork = ($masterFileCreator->getNumProcessed() !== 0);
			$this->_numErrors = $masterFileCreator->getNumErrors();
		}
		catch(Exception $e) {
			$this->_success = false;
			throw $e;
		}
	}


	protected function _getEmailSubjectLine()
	{
		if(!$this->_success) {
			return 'FOUT: Maken van master files onverwacht afgebroken';
		}
		if($this->_numErrors !== 0) {
			return "PAS OP: {$this->_numErrors} fouten bij het maken van de master files! Kijk in error map";
		}
		return 'SUCCES: Master files gemaakt';
	}

}
