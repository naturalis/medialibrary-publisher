<?php

namespace nl\naturalis\medialib\publisher\db\dao;

use \PDO;
use nl\naturalis\medialib\util\context\Context;

class WebPublisherDAO extends BaseDAO {


	public function __construct(Context $context)
	{
		parent::__construct($context);
	}


	public function setDirectoryAndFileName($id, $dir, $file)
	{
		$sql = 'UPDATE media SET www_dir=?,www_file=?,www_published=now(),www_ok=1 WHERE id=?';
		$stmt = $this->_pdo->prepare($sql);
		$stmt->bindValue(1, $dir);
		$stmt->bindValue(2, $file);
		$stmt->bindValue(3, $id);
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


	public function getUnprocessedMedia($producer)
	{
		$sql = 'SELECT id,regno,master_file FROM media WHERE producer=? AND master_ok=1 AND www_ok=0';
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