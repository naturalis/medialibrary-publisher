<?php

namespace nl\naturalis\medialib\publisher\offload;

require APPLICATION_PATH . '/vendor/autoload.php';

use nl\naturalis\medialib\publisher\common\ConfigChecker;
use nl\naturalis\medialib\publisher\PublisherObject;
use nl\naturalis\medialib\util\context\Context;
use nl\naturalis\medialib\util\DateTimeUtil;
use nl\naturalis\medialib\util\FileUtil;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 *
 * @author ayco_holleman, Ruud Altenburg
 */
class AwsStorageManager extends RemoteStorageManager {
	
	private $_awsClient;
	private $_fileList;
	
	public function __construct(Context $context)
	{
		parent::__construct($context);
	}

	/**
	 * Overrides method in RemoteStorageManager
	 */
	public function sendBatch()
	{
		$panicFile = $this->_context->getRequiredProperty('panicFile');
		PublisherObject::validatePanicFile($panicFile);
		
		$startTime = time();
		
		try {
			$this->_logger->addInfo('Offloading files to AWS');
			$this->_logger->addInfo('Local directory: ' . $this->_tarsDir);
			
			foreach (FileUtil::scandir($this->_tarsDir) as $file) {
				PublisherObject::checkPanicFile($panicFile);
				if ($file === '.' || $file === '..') {
					continue;
				}
				$this->_logger->addDebug('Offloading ' . $file);
				$result = $this->put($file);
				if (isset($result->error)) {
					throw new Exception('Could not put ' . $file . ' to AWS');
				}
				
				$this->_dao->setBackupOkForMediaFile(_getMediaFileId($file), $result);
			}
			
		} catch (Exception $e) {
			$this->_logStatistics($startTime);
			throw $e;
		}
		
		$this->_logStatistics($startTime);
	}
	
	public function setFileList (array $list)
	{
		$this->_fileList = $list;
	}

		
	public function put ($file) 
	{
		if (!$this->awsClient) {
			$this->_initAwsClient();
		}
		
		$localPath = $this->_tarsDir . DIRECTORY_SEPARATOR . $file;
		$bucket = $this->_config->offload->aws->bucket;
		$sha256 = hash_file('sha256', $localPath);
		$extension = FileUtil::getExtension($localPath, true);
		$key = trim(str_replace($extension, '', basename($localPath)), ". ");
		$result = new \stdClass;
		
		try {
			$awsResult = $this->awsClient->putObject([
				'Bucket'     => $bucket,
				'Key'        => $key,
				'SourceFile' => $localPath,
				"ContentSHA256" => $sha256,
				'Metadata' => [
					'file_name' => basename($localPath),
					'extension' => $extension,
					'mime_type' => mime_content_type($localPath),
				],
			]);
			$info = $awsData->get('@metadata');
			$result->sha256 = $sha256;
			$result->awsUri = isset($info['effectiveUri']) ? $info['effectiveUri'] : null;
			$result->created = isset($info['headers']['date']) ?
				date("Y-m-d H:i:s", strtotime($info['headers']['date'])) : null;
			
		} catch (S3Exception $e) {
			$message = "Unable to put $localPath to AWS: " . $e->getMessage();
			$this->_logger->addError($message);
			$result->error = $message;
		}
		
		return $result;
	}
	
	protected function _logStatistics($startTime)
	{
		$seconds = time() - $startTime;
		$this->_logger->addInfo('Time spent on offloading file to AWS: ' . 
			DateTimeUtil::hoursMinutesSeconds($seconds, true));
	}
	
	private function _initAwsClient () 
	{
		// Make sure AWS settings are present...
		$configChecker = new ConfigChecker($this->_context);
		$configChecker->checkConfig();
		
		$version = $this->_config->offload->aws->version;
		$region = $this->_config->offload->aws->region;
		$key = $this->_config->offload->aws->key;
		$secret = $this->_config->offload->aws->secret;
		
		try {
			$this->awsClient = new S3Client([
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
