<?php

namespace nl\naturalis\medialib\publisher\exception;

class FileNameTooLongException extends \Exception {

	public function __construct($regno, $maxLength)
	{
		parent::__construct('Invalid image ID: ' . $regno . ' (length exceeds ' . $maxLength . ' characters)');
	}

}
