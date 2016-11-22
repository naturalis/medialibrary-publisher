<?php

namespace nl\naturalis\medialib\publisher;

use \Exception;
use nl\naturalis\medialib\publisher\exception\UserInterruptException;
use nl\naturalis\medialib\publisher\exception\ConcurrentAccessException;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\EmailUtil;
use Monolog\Logger;
use nl\naturalis\medialib\publisher\exception\JoblessException;

/**
 * {@code PublisherObject} provides a template for the various types
 * of publishing tasks (see http://en.wikipedia.org/wiki/Template_method_pattern).
 * Subclasses fill in the blanks identified by the protected abstract
 * functions in {@code PublisherObject}  while clients should only use
 * the public API of the abstract base class (i.e. {@code PublisherObject}).
 * 
 * @author ayco_holleman
 *
 */
abstract class PublisherObject {


	public static function checkPanicFile($panicFile)
	{
		if(!file_exists($panicFile)) {
			throw new UserInterruptException();
		}
	}


	/**
	 * Check whether the pid found in the specified file is the same
	 * as the current pid. If not, we're in a pretty anomalous situation,
	 * because this method is meant to be called from a class
	 * instantiated somewhere in a {@code PublisherObject}'s _execute()
	 * method, and that method will not execute if the pids don't
	 * coincide. Thus this method is mainly here for classes participating
	 * in some publishing process to make absolutely sure they sing in the
	 * right choir.
	 */
	public static function validatePanicFile($panicFile)
	{
		self::checkPanicFile($panicFile);
		$chunks = explode('.', $panicFile);
		$process = $chunks[0];
		$discrimator = $chunks[1];
		$pid = file_get_contents($panicFile);
		if($pid != getmypid()) {
			$error = array();
			$error[] = "Another $process (pid: $pid) is still busy processing media files";
			$error[] = "for this data set ($discrimator). If you are sure this is not the";
			$error[] = "case, delete file $panicFile and start again.";
			throw new ConcurrentAccessException(implode(' ', $error));
		}
	}
	
	/**
	 * @var Context
	 */
	protected $_context;
	/**
	 * @var Logger
	 */
	protected $_logger;
	
	/**
	 * Subclasses can set this field to false to indicate that they
	 * effectively did nothing. If so, email notification will be
	 * suppressed.
	 * 
	 * @var boolean
	 */
	protected $_didWork = true;


	public function __construct($iniFile)
	{
		if(!defined('APPLICATION_PATH')) {
			throw new Exception('APPLICATION_PATH not defined.');
		}
		$this->_context = new Context(new Config($iniFile));
	}


	public function run()
	{
		// Pre-process		
		$this->_context->initializeLogging($this->_getLogFileFullPath());
		$this->_logger = $this->_context->getLogger(get_called_class());
		$panicFile = $this->_createPanicFile();
		if($panicFile === false) {
			/*
			$fmt = 'FOUT: %s onverwacht afgebroken voor %s %s';
			$subject = sprintf($fmt, $this->_getProcessName(), $this->_getDiscriminatorName(), $this->_getDiscriminatorValue());
			if(false === EmailUtil::sendDefaultEmail($this->_context, $subject)) {
				$this->_logger->addError('Email notification failed');
			}
			*/
			return;
		}
		// Process
		try {
			$this->_execute();
		}
		catch(UserInterruptException $e) {
			$this->_logger->addError($e->getMessage());
		}
		catch(ConcurrentAccessException $e) {
			$this->_didWork = false;
			$this->_logger->addError($e->getMessage());
		}
		catch(JoblessException $e) {
			$this->_didWork = false;
		}
		catch(Exception $e) {
			$this->_logger->addDebug("\n" . $e->getTraceAsString());
			$this->_logger->addError(basename($e->getFile()) . ' (' . $e->getLine() . '): ' . $e->getMessage());
		}
		// Post-process
		if(file_exists($panicFile)) {
			try {
				FileUtil::unlink($panicFile);
			}
			catch(Exception $e) {
				$this->_logger->addWarning("Could not remove panic file upon completion");
			}
		}
		
		if(!$this->_didWork) {
			$this->_logger->addInfo('Spurious run (no work done)');
		}
		
		$this->_logger->addDebug('Email notification: ' . $this->_getEmailSubjectLine());
		
		$this->_context->shutdown();
		// No Logging past this point!		
		


		if($this->_didWork) {
			$this->_sendEmail();
		}
	}


	/**
	 * The value used by a {@code PublisherObject} to retrieve
	 * a unique set of data to work on. Other instances of the
	 * same subclass {@code PublisherObject} may run in parallel
	 * with this instance only if their discriminator value is
	 * different. For example, multiple {@code Offloader}s may
	 * run concurrently, but they must all work on a different
	 * backup group. Likewise, multiple {@code Harvester}s may
	 * run concurrently, but they must all process a different
	 * producer.
	 */
	protected abstract function _getDiscriminatorValue();


	/**
	 * Provide a user-friendly name for the "thing" that separates
	 * concurrently running instances of the same subclass of
	 * {@code PublisherObject}. Used only for reporting purposes.
	 */
	protected abstract function _getDiscriminatorName();


	/**
	 * Do the thing that you are supposed to do.
	 */
	protected abstract function _execute();


	protected abstract function _getEmailSubjectLine();


	private function _getLogFileName()
	{
		$chunks = array();
		$chunks[] = date('YmdHis');
		$chunks[] = $this->_getProcessName();
		$chunks[] = $this->_getDiscriminatorValue();
		$chunks[] = 'log';
		return implode('.', $chunks);
	}


	private function _getLogFileFullPath()
	{
		$dir = $this->_context->getConfig()->logDirectory;
		if(!is_dir($dir)) {
			throw new Exception("Logging directory does not exist: $dir");
		}
		return FileUtil::createPath($dir, $this->_getLogFileName());
	}


	private function _createPanicFile()
	{
		$process = $this->_getProcessName();
		$discriminator = $this->_getDiscriminatorValue();
		$fileName = $process . '.' . $discriminator . '.pid';
		$path = FileUtil::createPath(sys_get_temp_dir(), $fileName);
		if(is_file($path)) {
			$pid = file_get_contents($path);
			$error = array();
			$error[] = "Another $process (pid: $pid) is still busy processing media from";
			$error[] = "this {$this->_getDiscriminatorName()} $discriminator. If you are";
			$error[] = "sure this is not the case, delete file $path and start again.";
			$this->_logger->addError(implode(' ', $error));
			return false;
		}
		else {
			if(file_put_contents($path, getmypid()) === false) {
				$this->_logger->addError("Could not create panic file: $path");
				return false;
			}
			$this->_context->setProperty('panicFile', $path);
			$this->_logger->addInfo("To abort process, delete file: $path");
			return $path;
		}
	}


	private function _sendEmail()
	{
		$mailTo = explode(',', $this->_context->getConfig()->mail->to);
		if(count($mailTo) === 0) {
			echo "No emails sent";
			return;
		}
		$mail = new \PHPMailer();
		foreach($mailTo as $address) {
			$mail->AddAddress($address);
		}
		$mail->SetFrom('medialib@naturalis.nl', 'NBC Media Library');
		$mail->Subject = $this->_getEmailSubjectLine();
		if(is_file($this->_context->getLogFile())) {
			$mail->Body = file_get_contents($this->_context->getLogFile());
			$mail->AddAttachment($this->_context->getLogFile());
		}
		else {
			$mail->Body = 'Log file not available: ' . $this->_context->getLogFile();
		}
		if(!$mail->Send()) {
			echo "Email notification failed";
		}
	}


	private function _getProcessName()
	{
		return substr(get_called_class(), strrpos(get_called_class(), '\\') + 1);
	}

}