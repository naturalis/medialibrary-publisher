<?php

namespace nl\naturalis\medialib\util\logging;

use Monolog\Logger;
use nl\naturalis\medialib\util\logging\BasicMonologHandler;

class LoggerFactory {
	
	/**
	 *
	 * @var BasicMonologHandler
	 */
	private $_logHandler;
	private $_loggers;


	public function __construct(BasicMonologHandler $logHandler)
	{
		$this->_logHandler = $logHandler;
	}


	/**
	 * Return a logger with the specified name. You should ordinarily specify
	 * __CLASS__ for the logger's name.
	 *
	 * @return Logger
	 */
	public function getLogger($name)
	{
		if(isset($this->_loggers[$name])) {
			return $this->_loggers[$name];
		}
		$logger = new Logger($name);
		$logger->pushHandler($this->_logHandler);
		return ($this->_loggers[$name] = $logger);
	}

}