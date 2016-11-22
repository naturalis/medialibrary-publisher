<?php

namespace nl\naturalis\medialib\publisher\exception;

use nl\naturalis\medialib\util\Command;

/**
 * An {@code Exception} indicating that the ImageMagick command
 * issued for a particular media file resulted in an error.
 *
 * @author ayco_holleman
 *
 */
class ImageMagickException extends \Exception {
	private $_mediaFile;
	/**
	 * 
	 * @var Command
	 */
	private $_command;


	public function __construct($mediaFile, Command $command)
	{
		parent::__construct(sprintf('Error while processing image "%s": %s', $mediaFile, $command->getOutputAsString()));
		$this->_mediaFile = $mediaFile;
		$this->_command = $command;
	}


	public function getMediaFile()
	{
		return $this->_mediaFile;
	}


	/**
	 * @return Command
	 */
	public function getCommand()
	{
		return $this->_command;
	}

}