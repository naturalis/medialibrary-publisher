<?php

namespace nl\naturalis\medialib\publisher\offload;

require APPLICATION_PATH . '/vendor/autoload.php';

use \Exception;
use nl\naturalis\medialib\publisher\common\ConfigChecker;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\publisher\db\dao\HarvesterDAO;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 *
 * @author ayco_holleman, Ruud Altenburg
 */
class AwsStorageManager {
	
	private $_context;
	private $_logger;
	private $_config;
	private $_dao;
	private $_awsClient;
	private $_backupGroup;
	private $_fileList = [];
	
	public function __construct (Context $context, $backupGroup = null)
	{
		// RemoteStorage construct
		$this->_context = $context;
		$this->_config = $context->getConfig();
		$this->_logger = $context->getLogger(__CLASS__);
		$this->_dao = new HarvesterDAO($context);
		$time = $context->getRequiredProperty('start');
		
		// Backup group is required, better make sure it's there
		if (is_null($backupGroup)) {
			throw new Exception('AWS error: backup group not set!');
		}
		$this->_backupGroup = $backupGroup;

		// Check config for AWS settings
		$configChecker = new ConfigChecker($this->_context);
		$configChecker->checkConfig();
	}
	
	public function getOffloadableMedia ()
	{
		// Was the process interrupted before we even got this far?
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		try {
			
			$this->_logger->addDebug("Retrieving media for backup group {$this->_backupGroup}");
			$stmt = $this->_dao->getOffloadableMedia($this->_backupGroup);
			while ($media = $stmt->fetch()) {
				PublisherObject::checkPanicFile($panicFile);
				$path = $media->source_file;
				if (!is_file($path)) {
					$this->_logger->addWarning("Stale database record (no such file: \"$path\"). " .
						"Record will be removed from media database.");
					$this->_dao->deleteMedia($media->id);
					continue;
				}
				$this->_fileList[$media->id] = $media->source_file;
			}
		
		} catch (Exception $e) {
			throw $e;
		}
		
		return $this->_fileList;
	}
	
	public function getFileList () 
	{
		if (!empty($this->_fileList)) {
			return $this->_fileList;
		}
		return $this->getOffloadableMedia();
	}

	public function putFiles ()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		try {
			$this->_logger->addInfo('Offloading ' . count($this->getFileList()) . 
				' files from backup group ' .  $this->_backupGroup . ' to AWS');
			
			foreach ($this->getFileList() as $file) {
				PublisherObject::checkPanicFile($panicFile);
				$this->_logger->addDebug('Offloading ' . $file);
				$result = $this->put($file);
				if (isset($result->error)) {
					throw new Exception('Could not put ' . $file . ' to AWS: ' . $result->error);
				}
				$this->_dao->setBackupOkForMediaFile($this->_getMediaFileId($file), $result);
			}
			
		} catch (Exception $e) {
			$this->_logStatistics($startTime);
			throw $e;
		}
		
		$this->_logStatistics($startTime);
	}
	
	public function put ($file) 
	{
		$result = new \stdClass;
		
		// Double-check if file actually exists
		if (!is_file($file)) {
			$result->error = "Could not put $file to AWS: file does not exist!";
			return $result;
		}
		
		if (!$this->_awsClient) {
			$this->_initAwsClient();
		}
		
		$extension = FileUtil::getExtension($file, true);
		$key = ;
		
		try {			
			$awsResult = $this->_awsClient->putObject([
				'Bucket'        => $this->_config->offload->aws->bucket,
				'Key'           => trim(str_ireplace($extension, '', basename($file)), ". "),
				'SourceFile'    => $file,
				"ContentSHA256" => hash_file('sha256', $file),
				'Content-Type'  => mime_content_type($file),
				'Metadata'      => [
					'Original-File-Name' => basename($file),
					'Original-File-Extension' => $extension,
				],
			]);
			$info = $awsResult->get('@metadata');
			$result->etag = str_replace('"', '', $awsResult->get('ETag'));
			$result->awsUri = isset($info['effectiveUri']) ? $info['effectiveUri'] : null;
			$result->created = isset($info['headers']['date']) ?
				date("Y-m-d H:i:s", strtotime($info['headers']['date'])) : null;
			
		} catch (S3Exception $e) {
			$message = "Could not put $file to AWS: : " . $e->getMessage();
			$this->_logger->addError($message);
			$result->error = $message;
		}
		
		return $result;
	}
	
	private function _logStatistics ($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on offloading files to AWS: ' . 
			DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}
	
	private function _initAwsClient () 
	{
		$version = $this->_config->offload->aws->version;
		$region = $this->_config->offload->aws->region;
		$key = $this->_config->offload->aws->key;
		$secret = $this->_config->offload->aws->secret;
		
		try {
			$this->_awsClient = new S3Client([
				'version'     => $version,
				'region'      => $region,
				'credentials' => [
					'key'    => $key,
	        		'secret' => $secret,					
				],
			]);
			
		} catch (S3Exception $e) {
			throw $e;
		}
		
		return $this->_awsClient;
	}
	
	private function _getMediaFileId ($file)
	{
		// The safe way
		if (isset($this->_fileList)) {
			$id = array_search($file, $this->_fileList);
			if ($id) {
				return $id;
			}
		}
		// Assume id is start of the file name
		$tmp = explode('-', basename($file));
		return ltrim(reset($tmp), 0);
	}
	
}
