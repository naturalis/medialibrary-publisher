<?php

namespace nl\naturalis\medialib\publisher\offload;

use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\db\dao\HarvesterDAO;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use nl\naturalis\medialib\util\Config;
use Monolog\Logger;

/**
 *
 * @author ayco_holleman
 */
class RemoteStorageManager {
	
	/**
	 * 
	 * @var Context
	 */
	private $_context;
	/**
	 *
	 * @var Logger
	 */
	private $_logger;
	/**
	 *
	 * @var Config
	 */
	private $_config;
	/**
	 *
	 * @var HarvesterDAO
	 */
	private $_dao;
	/**
	 *
	 * @var resource
	 */
	private $_conn;
	/**
	 *
	 * @var string
	 */
	private $_tarsDir;
	/**
	 *
	 * @var string
	 */
	private $_remoteDir;


	public function __construct(Context $context)
	{
		$this->_context = $context;
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new HarvesterDAO($context);
		$time = $context->getRequiredProperty('start');
		$this->_remoteDir = date('Ymd', $time);
	}


	/**
	 * Set directory containing the tar files.
	 *
	 * @param string $dir
	 */
	public function setTarsDirectory($dir)
	{
		$this->_tarsDir = $dir;
	}


	/**
	 * Closes the FTP connection opened by thie OfflineStorageManager. If no
	 * connection had been opened yet, this is a no-op.
	 */
	public function closeConnection()
	{
		if(is_resource($this->_conn)) {
			@ftp_close($this->_conn);
		}
		$this->_conn = null;
	}


	/**
	 * Get the absolute path to the directory on the remote server into which to
	 * place the tar files. This method is here so that external code could be
	 * passed the return value of this method and they could then issue an ftp
	 * cd command which is guaranteed to take them to the directory in which
	 * they can find/put tar files. More specifically, it is here so that we can
	 * pass the custom unix script that can optionally be used to do the tarring
	 * and offloading in one shot an unambiguous path to which to cd. This
	 * method will open an FTP connection if none has been created yet, but it
	 * will not close it. Therefore you should call {@link closeConnection} if
	 * all you want from the OfflineStorageManager is the absolute path of the
	 * tar director on the remote server.
	 *
	 * @return string
	 */
	public function getRemoteDirectoryAbsolutePath()
	{
		if(!$this->_connected()) {
			// Note that _connect will not just connect to the ftp server,
			// but also change directory to (and create if necessary) the
			// subdirectory into which to put the tar files.
			$this->_connect();
		}
		// Therefore, this is correct:
		return ftp_pwd($this->_conn);
	}


	public function getRemoteDirectoryRelativePath()
	{
		$initDir = $this->_getInitDir();
		if($initDir === null) {
			return $this->_remoteDir;
		}
		return $initDir . '/' . $this->_remoteDir;
	}


	/**
	 * This method iterates over a directory with tar files and sends them all
	 * to the backup server. You would use this method if you think it's best to
	 * first create all tar files and only then send them off. If you think the
	 * best policy is to offload a tar file right after it has been created, use
	 * {@link #send}.
	 */
	public function sendBatch()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		try {
			$this->_logger->addInfo('Offloading tar files');
			$this->_logger->addInfo('Local directory: ' . $this->_tarsDir);
			$this->_logger->addInfo('Remote directory: ' . $this->getRemoteDirectoryRelativePath());
			$this->_connect();
			foreach(FileUtil::scandir($this->_tarsDir) as $tarFile) {
				PublisherObject::checkPanicFile($panicFile);
				if($tarFile === '.' || $tarFile === '..') {
					continue;
				}
				if(FileUtil::getExtension($tarFile, true) !== 'tar') {
					// Huh?
					$this->_logger->addWarning("Ignoring $tarFile");
					continue;
				}
				$this->_logger->addDebug('Offloading ' . $tarFile);
				$this->_offload($tarFile);
				if(!$this->hasArrived($tarFile)) {
					throw new Exception('Tar file did not arrive on remote server: ' . $tarFile);
				}
				$this->_dao->setBackupOkForAllMediaInTarFile($tarFile);
			}
			$this->closeConnection();
		}
		catch(Exception $e) {
			$this->_logStatistics($startTime);
			$this->closeConnection();
			throw $e;
		}
		
		$this->_logStatistics($startTime);
	}


	/**
	 * Offload one tar file. You only specify the simple name of the tar file.
	 * The directory containing the tar file must have been set before using
	 * {@link setTarsDirectory}
	 *
	 * @param string $tarFile The simple name of the tar file.
	 */
	public function send($tarFile)
	{
		$this->_logger->addDebug('Offloading ' . $tarFile);
		$this->_offload($tarFile);
	}


	public function hasArrived($tarFile)
	{
		ftp_close($this->_conn);
		$this->_connect();
		$size = ftp_size($this->_conn, $tarFile);
		if($size == -1 || $size === false || $size === null || $size === '') {
			return false;
		}
		else {
			$mb = round(($size / (1024 * 1024)), 2);
			$this->_logger->addInfo("Size of $tarFile on remote server: $size bytes ($mb MB)");
			return true;
		}
	}


	private function _offload($tarFile)
	{
		$maxAttempts = max(1, (int) $this->_config->offload->ftp->maxUploadAttempts);
		for($i = 0; $i < $maxAttempts; ++$i) {
			if($this->_offloadOnce($tarFile)) {
				return;
			}
			sleep(1);
		}
		throw new \Exception("Could not offload $tarFile in $maxAttempts attempts");
	}


	private function _offloadOnce($tarFile)
	{
		$localPath = $this->_tarsDir . DIRECTORY_SEPARATOR . $tarFile;
		if(!$this->_connected() || $this->_config->getBoolean('offload.ftp.reconnectPerFile')) {
			$this->_connect();
		}
		if(@ftp_put($this->_conn, $tarFile, $localPath, FTP_BINARY)) {
			$tarFileId = $this->_dao->getTarFileId($tarFile);
			return true;
		}
		else {
			$errInfo = error_get_last();
			$this->_logger->addWarning("Failed to offload $tarFile: " . $errInfo['message']);
			return false;
		}
	}


	private function _connect()
	{
		$x = (int) $this->_config->offload->ftp->maxConnectionAttempts;
		for($i = 0; $i < $x; ++$i) {
			if($this->_connectOnce()) {
				return;
			}
			sleep(2);
		}
		throw new \Exception("Could not establish FTP connection in $x attempts");
	}


	private function _connectOnce()
	{
		// If one was open already, close it first
		$this->closeConnection();
		
		$host = $this->_config->offload->ftp->host;
		$user = $this->_config->offload->ftp->user;
		$pw = $this->_config->offload->ftp->password;
		
		$conn = ftp_connect($host);
		if($conn === false) {
			$this->_logger->addWarning('Connection attempt failed');
			return false;
		}
		$ok = @ftp_login($conn, $user, $pw);
		if($ok === false) {
			$this->_logger->addWarning('FTP login failed');
			return false;
		}
		
		$passive = $this->_config->getBoolean('offload.ftp.passive');
		$this->_logger->addDebug('FTP passive mode (offload.ftp.passive): ' . ($passive ? 'yes' : 'no'));
		$ok = @ftp_pasv($conn, $passive);
		if($ok === false) {
			$this->_logger->addWarning(sprintf('Could not switch to FTP %s mode', $passive ? 'passive' : 'active'));
			return false;
		}
		
		$initDir = $this->_getInitDir();
		if($initDir !== null) {
			$this->_logger->addDebug("Changing directory to \"$initDir\" on remote server");
			if(@ftp_chdir($conn, $initDir) === false) {
				$this->_logger->addDebug("Directory does not exist. Creating root directory \"$initDir\" on remote server");
				if(!@ftp_mkdir($conn, $initDir)) {
					$errInfo = error_get_last();
					throw new \Exception("Could not create root directory \"$initDir\" on remote server: " . $errInfo['message']);
				}
			}
			if(@ftp_chdir($conn, $initDir) === false) {
				$errInfo = error_get_last();
				throw new \Exception("Could not access remote root directory \"$initDir\": " . $errInfo['message']);
			}
		}
		
		// Now we should be in the configured TOP directory for all tar files		
		


		// Do some not very useful stuff just to make sure the FTP server
		// behaves as expected, and log some more info.
		$pwd = @ftp_pwd($conn);
		if($pwd === false) {
			throw new \Exception('Failed to get present working directory on remote server');
		}
		$this->_logger->addDebug("Present working directory on remote server: \"$pwd\"");
		
		$this->_logger->addDebug("Changing directory to \"{$this->_remoteDir}\" on remote server");
		if(@ftp_chdir($conn, $this->_remoteDir) === false) {
			$this->_logger->addDebug("Directory does not exist. Creating subdirectory \"{$this->_remoteDir}\" on remote server");
			if(!@ftp_mkdir($conn, $this->_remoteDir)) {
				$errInfo = error_get_last();
				throw new \Exception("Could not create subdirectory \"{$this->_remoteDir}\" on remote server: " . $errInfo['message']);
			}
			if(@ftp_chdir($conn, $this->_remoteDir) === false) {
				$errInfo = error_get_last();
				throw new \Exception("Could not access subdirectory \"{$this->_remoteDir}\" on remote server: " . $errInfo['message']);
			}
		}
		
		// Now we should be in the direcotry into which to place that tar files for today
		


		$pwd = @ftp_pwd($conn);
		if($pwd === false) {
			throw new \Exception('Failed to get present working directory on remote server');
		}
		$this->_logger->addDebug("Present working directory on remote server: \"$pwd\"");
		
		$this->_conn = $conn;
		return true;
	}


	private function _connected()
	{
		return is_resource($this->_conn);
	}


	private function _getInitDir()
	{
		$dir = $this->_config->offload->ftp->initDir;
		if($dir === null || $dir === '.' || $dir === '/') {
			return null;
		}
		return $dir;
	}


	private function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on offloading tar files: ' . DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}

}
