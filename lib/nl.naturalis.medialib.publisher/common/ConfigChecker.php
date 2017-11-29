<?php

namespace nl\naturalis\medialib\publisher\common;

use \Exception;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\context\Context;

class ConfigChecker {
	

	/**
	 * @var Config
	 */
	private $_config;
	/**
	 * @var Logger
	 */
	private $_logger;


	public function __construct(Context $context)
	{
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
	}


	public function checkConfig()
	{
		$this->_logger->addDebug('Validating configuration');
		
		$producer = $this->_config->producer;
		if(strlen($producer) > 64) {
			throw new Exception('Configuration error: producer name may not exceed 64 characters');
		}
		if(preg_match('/[^a-zA-Z0-9_-]/', $producer)) {
			throw new Exception('Configuration error: invalid character(s) in producer name');
		}
		
		$owner = $this->_config->owner;
		if(strlen($owner) > 64) {
			throw new Exception('Configuration error: owner name may not exceed 64 characters');
		}
		if(preg_match('/[^a-zA-Z0-9_-]/', $owner)) {
			throw new Exception('Configuration error: invalid character(s) in owner name');
		}
		
		$dirs = array(
				'harvestDirectory',
				'duplicatesDirectory',
				'resubmitDirectory',
				'stagingDirectory',
				'masterDirectory',
				'wwwDirectory',
				'logDirectory',
				'deadImagesDirectory'
		);
		
		foreach($dirs as $dir) {
			if(preg_match('/[\s]/', $dir)) {
				throw new Exception('Configuration error: no whitespace allowed in directory paths ( ' . $dir . ')');
			}
		}
		
		$aws = ['region', 'bucket', 'key', 'secret', 'version'];
		foreach ($aws as $setting) {
			if (!isset($this->_config->offload->aws->{$setting}) || 
				empty($this->_config->offload->aws->{$setting})) {
				throw new Exception('Configuration error: offload.aws. ' . $setting . ' not set');
			}
		}
		
		
		
		// More checks ...
	}

}
