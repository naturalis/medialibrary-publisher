<?php

namespace nl\naturalis\medialib\publisher\db\dao;

use \PDO;
use nl\naturalis\medialib\util\context\Context;

class MasterPublisherDAO extends BaseDAO {


	public function __construct(Context $context)
	{
		parent::__construct($context);
	}


	public function setMasterFile($id, $path)
	{
		$sql = 'UPDATE media SET master_file=?,master_published=now(),master_ok=1 WHERE id=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $path);
		$stmt->bindValue(2, $id);
		$this->_executeStatement($stmt);
	}


	public function producerExists($name)
	{
		$sql = 'SELECT 1 FROM media WHERE producer=? LIMIT 1';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $name);
		$this->_executeStatement($stmt);
		return $stmt->fetch() !== false;
	}


	public function getProducer($mediaId)
	{
		$sql = 'SELECT producer FROM media WHERE id=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $mediaId);
		$this->_executeStatement($stmt);
		return $stmt->fetchColumn();
	}


	public function getUnprocessedMedia($producer)
	{
		$sql = 'SELECT id,regno,source_file FROM media WHERE producer=? and master_ok=0';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $producer);
		$this->_executeStatement($stmt);
		// For now, let's just return the actual records, rather
		// then the statement itself. Might be faster than having
		// the client repeatedly calling fetch on the statement,
		// and might also provide more transaction isolation when
		// running multiple instances of the master publisher.
		// Even, say, 100,000 records won't blow up RAM.
		return $stmt->fetchAll(PDO::FETCH_OBJ);
	}

}