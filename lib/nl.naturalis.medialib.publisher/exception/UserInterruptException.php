<?php

namespace nl\naturalis\medialib\publisher\exception;

class UserInterruptException extends \Exception {

	const DEFAULT_MESSAGE = 'Process interrupted by user';
	
	public function __construct()
	{
		parent::__construct(self::DEFAULT_MESSAGE);
	}

}