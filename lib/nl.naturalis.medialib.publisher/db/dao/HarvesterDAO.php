<?php

namespace nl\naturalis\medialib\publisher\db\dao;

use \Exception;
use \PDO;
use \PDOStatement;
use nl\naturalis\medialib\util\context\Context;

class HarvesterDAO extends BaseDAO {
	
	/**
	 * 
	 * @var PDOStatement
	 */
	private $_newMediaFileStmt;
	/**
	 *
	 * @var PDOStatement
	 */
	private $_resetStatusStmt;


	public function __construct(Context $context)
	{
		parent::__construct($context);
		$this->_newMediaFileStmt = $this->_pdo->prepare('INSERT INTO media(regno) VALUES(?)');
		$this->_resetStatusStmt = $this->_pdo->prepare('
				 UPDATE media
				    SET producer=?,
				        owner=?,
				        source_file=?,
				        source_file_size=?,
				        backup_group=?,
				        source_file_created=now(),
				        backup_ok=0,
				        master_ok=0,
				        www_ok=0
				  WHERE id=?');
	}


	public function getTarFileId($tarFileName)
	{
		$sql = 'SELECT id FROM tar_file WHERE name=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $tarFileName);
		$this->_executeStatement($stmt);
		return $stmt->fetchColumn();
	}


	public function newMediaFile($regno)
	{
		$this->_newMediaFileStmt->bindValue(1, $regno);
		$this->_executeStatement($this->_newMediaFileStmt);
		return $this->_pdo->lastInsertId();
	}


	public function resetStatus($id, $producer, $owner, $sourceFile, $fileSize, $backupGroup)
	{
		$stmt = $this->_resetStatusStmt;
		$stmt->bindValue(1, $producer);
		$stmt->bindValue(2, $owner);
		$stmt->bindValue(3, $sourceFile);
		$stmt->bindValue(4, $fileSize);
		$stmt->bindValue(5, $backupGroup);
		$stmt->bindValue(6, $id);
		$this->_executeStatement($stmt);
	}


	/**
	 * Get all media files within a backup group that have not been backed up yet.
	 * 
	 * @param int $backupGroup
	 * @return PDOStatement
	 */
	public function getOffloadableMedia($backupGroup)
	{
		$sql = 'SELECT id,source_file FROM media WHERE backup_group=? AND backup_ok=0';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $backupGroup);
		$this->_executeStatement($stmt);
		$stmt->setFetchMode(PDO::FETCH_OBJ);
		return $stmt;
	}

	public function registerTarFile($name, $remoteDirectory)
	{
		$sql = 'INSERT INTO tar_file(name,remote_dir,backup_created) VALUES(?,?,now())';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $name);
		$stmt->bindValue(2, $remoteDirectory);
		$this->_executeStatement($stmt);
		return $this->_pdo->lastInsertId();
	}


	public function setTarFile($id, $tarFileId, $backupComplete)
	{
		$sql = 'UPDATE media SET tar_file_id=?,backup_ok=? WHERE id=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $tarFileId);
		$stmt->bindValue(2, $backupComplete ? 1 : 0);
		$stmt->bindValue(3, $id);
		$this->_executeStatement($stmt);
		if($stmt->rowCount() === 0) {
			return false;
		}
		return true;
	}


	public function setBackupOkForAllMediaInTarFile($tarFile)
	{
		$sql = 'UPDATE media a JOIN tar_file b ON(a.tar_file_id=b.id) SET a.backup_ok=1 WHERE b.name=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $tarFile);
		$this->_executeStatement($stmt);
		if($stmt->rowCount() === 0) {
			throw new Exception("No tar file registered with name \"$tarFile\"");
		}
	}
	
	public function setBackupOkForMediaFile ($id, $fileInfo)
	{
$this->_logger->addDebug("Result object: " . var_dump($fileInfo));
		$sql = 'UPDATE media 
			SET source_file_etag = ?, source_file_aws_uri = ?, source_file_backup_created = ?, backup_ok = 1 
			WHERE id = ?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $fileInfo->etag);
		$stmt->bindValue(2, $fileInfo->awsUri);
		$stmt->bindValue(3, $fileInfo->created);
		$stmt->bindValue(4, $id);
		$this->_executeStatement($stmt);
$this->_logger->addDebug("Database query: " . $stmt->debugDumpParams());
		if ($stmt->rowCount() === 0) {
			throw new Exception("Could not set source file AWS backup data for file id: $id");
		}
		
	}

}