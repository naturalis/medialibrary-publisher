<?php

namespace nl\naturalis\medialib\publisher\exception;

class DuplicateMediaFileException extends \Exception {
	private $_path;


	public function __construct($path)
	{
		parent::__construct('Duplicate media file: ' . basename($path));
		$this->_path = $path;
	}


	public function getPath()
	{
		return $this->_path;
	}

}
