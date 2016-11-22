<?php

namespace nl\naturalis\medialib\publisher\exception;

class MediaNotFoundException extends \Exception {
	private $_path;


	public function __construct($path)
	{
		parent::__construct('No record found for media file: ' . basename($path) . ' (The file may have been submitted as an update, but was in fact new or deleted)');
		$this->_path = $path;
	}


	public function getPath()
	{
		return $this->_path;
	}

}