<?php

namespace nl\naturalis\medialib\util\logging;

use Monolog\Logger;
use Monolog\Handler\AbstractHandler;

class BasicMonologHandler extends AbstractHandler
{
	private $_stdout = false;
	private $_fh = null;

	public function __construct ($level = Logger::DEBUG, $bubble = true)
	{
		parent::__construct($level, $bubble);
	}

	public function close ()
	{
		if (is_resource($this->_fh)) {
			fclose($this->_fh);
		}
	}

	public function handle (array $record)
	{
		$msg = $this->getFormatter()->format($record);
		if ($this->_stdout === true) {
			echo $msg;
		}
		if ($this->_fh !== null) {
			fwrite($this->_fh, $msg);
		}
	}

	public function setStdout ($_stdout)
	{
		$this->_stdout = $_stdout;
	}

	public function setLogFile ($file)
	{
		//echo "\nLog file: \"$file\"\n";
		$this->_fh = @fopen($file, 'a');
		if ($this->_fh === false) {
			$err = error_get_last();
			echo "\nCould not create/open log file \"$file\": {$err[1]}\n";
			exit();
		}
	}

}