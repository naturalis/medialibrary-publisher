<?php

namespace nl\naturalis\medialib\publisher\db\dao;

use \PDO;
use \PDOStatement;
use nl\naturalis\medialib\util\Config;
use nl\naturalis\medialib\util\context\Context;
use Monolog\Logger;

class BaseDAO {
	
	/**
	 *
	 * @var Logger
	 */
	protected $_logger;
	/**
	 *
	 * @var PDO
	 */
	protected $_pdo;
	

	/**
	 * 
	 * @var PDOStatement
	 */
	private $_getMediaIdtmt;
	/**
	 * 
	 * @var PDOStatement
	 */
	private $_deleteMediaStmt;


	public function __construct(Context $context)
	{
		$this->_pdo = $context->getSharedPDO();
		$this->_logger = $context->getLogger(get_called_class());
		$this->_getMediaIdtmt = $this->_pdo->prepare('SELECT id FROM media WHERE regno=?');
		$this->_deleteMediaStmt = $this->_pdo->prepare('DELETE FROM media WHERE id=?');
	}


	public function checkDatabase()
	{
		$sql = 'SELECT * FROM media LIMIT 1';
		$stmt = $this->_pdo->prepare($sql);
		$this->_executeStatement($stmt);
		// Allow exception to be thrown
	}


	/**
	 * Look up media id using regno
	 * @param string $regno
	 * @return string
	 */
	public function getMediaId($regno)
	{
		$stmt = $this->_getMediaIdtmt;
		$stmt->bindValue(1, $regno);
		$this->_executeStatement($stmt);
		return $stmt->fetchColumn();
	}


	/**
	 * Get media record with specified regno
	 * @param string $regno
	 * @return \stdClass
	 */
	public function getMediaByRegno($regno)
	{
		$sql = 'SELECT * FROM media WHERE regno=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $regno);
		$this->_executeStatement($stmt);
		return $stmt->fetch(PDO::FETCH_OBJ);
	}


	/**
	 * Get media record with specified id
	 * @param int $id
	 * @return \stdClass
	 */
	public function getMediaById($id)
	{
		$sql = 'SELECT * FROM media WHERE id=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $id);
		$this->_executeStatement($stmt);
		return $stmt->fetch(PDO::FETCH_OBJ);
	}


	/**
	 * Get tar_file record with specified id
	 * @param int $id
	 * @return \stdClass
	 */
	public function getTarFileById($id)
	{
		$sql = 'SELECT * FROM tar_file WHERE id=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $id);
		$this->_executeStatement($stmt);
		return $stmt->fetch(PDO::FETCH_OBJ);
	}


	/**
	 * Delete media record with specified id
	 * @param int $id
	 */
	public function deleteMedia($id)
	{
		$stmt = $this->_deleteMediaStmt;
		$stmt->bindValue(1, $id);
		$this->_executeStatement($stmt);
	}


	/**
	 * Copy a "life" media record to the deleted_media table. The argument
	 * passed to this method must be a complete record from the media table,
	 * fetched using PDO::FETCH_OBJ.
	 */
	public function backup($media)
	{
		$columns = array(
				'id',
				'regno',
				'producer',
				'owner',
				'source_file',
				'source_file_created',
				'master_file',
				'master_published',
				'www_dir',
				'www_file',
				'www_published',
				'tar_file_id',
				'backup_group',
				'backup_ok',
				'master_ok',
				'www_ok'
		);
		// Create INSERT statement
		$sql = 'INSERT INTO deleted_media(';
		$sql .= implode(',', $columns);
		$sql .= ')VALUES(';
		$sql .= array_reduce($columns, function ($result, $item)
		{
			if($result === null) {
				return '?';
			}
			return $result . ',?';
		});
		$sql .= ')';
		// Prepare
		$stmt = $this->_pdo->prepare($sql);
		// Bind
		foreach($columns as $i => $col) {
			if(!property_exists($media, $col)) {
				throw new \Exception('Error while creating backup for media record. No such column: ' . $col);
			}
			$stmt->bindValue($i + 1, $media->$col);
		}
		// Execute
		$this->_executeStatement($stmt);
	}


	protected function _executeStatement(PDOStatement $stmt)
	{
		if($stmt->execute() === false) {
			$info = $stmt->errorInfo();
			throw new \Exception($info[2]);
		}
	}

}