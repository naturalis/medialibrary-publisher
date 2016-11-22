<?php

namespace nl\naturalis\medialib\publisher;

use nl\naturalis\medialib\publisher\web\WebFileCreator;
use nl\naturalis\medialib\util\EmailUtil;


/**
 * Driver class for the process that creates/publishes the web resources.
 * 
 * @author ayco_holleman
 */
class WebPublisher extends PublisherObject {
	const ACTION_MOVE = 1;
	const ACTION_DERIVE = 2;
	
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
		$webFileCreator = new WebFileCreator($this->_context);
		try {
			$webFileCreator->createWebFiles();
			$this->_didWork = ($webFileCreator->getNumProcessed() !== 0);
			$this->_numErrors = $webFileCreator->getNumErrors();
		}
		catch(\Exception $e) {
			$this->_success = false;
			throw $e;
		}
	}


	protected function _getEmailSubjectLine()
	{
		if(!$this->_success) {
			return 'FOUT: Maken van afgeleiden onverwacht afgebroken';
		}
		if($this->_numErrors !== 0) {
			return "PAS OP: {$this->_numErrors} fouten bij het maken van de afgeleiden! Kijk in error map";
		}
		return 'SUCCES: Afgeleides gemaakt en naar medialib verzonden';
	}
}