<?php

namespace nl\naturalis\medialib\util;


/** 
 * 
 * Wrapper class for PHP's exec() function.
 * 
 * @author ayco_holleman
 * 
 */
class Command {
	private $_command;
	private $_output;
	private $_returnValue;


	public function __construct($command)
	{
		$this->_command = $command . ' 2>&1';
	}


	public function execute()
	{
		$this->_output = array();
		$this->_returnValue = null;
		exec($this->_command, $this->_output, $this->_returnValue);
		return $this->_returnValue;
	}


	public function getCommandLine()
	{
		return $this->_command;
	}


	public function getReturnValue()
	{
		return $this->_returnValue;
	}


	public function getOutputAsString($concatenator = "\n")
	{
		return implode($concatenator, $this->_output);
	}


	public function getOutputAsArray()
	{
		return $this->_output;
	}

}