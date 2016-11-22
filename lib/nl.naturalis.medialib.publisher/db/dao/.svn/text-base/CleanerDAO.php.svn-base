<?php

namespace nl\naturalis\medialib\publisher\db\dao;

use \Exception;
use \PDO;
use \PDOStatement;
use nl\naturalis\medialib\util\context\Context;
use Monolog\Logger;

class CleanerDAO extends BaseDAO {
	
	/**
	 * The PDOStatement for the {@link #getStatus()} query.
	 * Since we are going to execute it a whole lot of times,
	 * we cache it.
	 *
	 * @var PDOStatement
	 */
	private $_pdoStmt0;


	public function __construct(Context $context)
	{
		parent::__construct($context);
		$this->_pdoStmt0 = $this->_pdo->prepare('SELECT date_format(source_file_created, \'%Y%m%d\') as source_file_created, backup_ok, master_ok, www_ok FROM media WHERE id = ?');
	}


	public function getStatus($id)
	{
		$this->_pdoStmt0->bindValue(1, $id);
		$this->_executeStatement($this->_pdoStmt0);
		return $this->_pdoStmt0->fetch(PDO::FETCH_OBJ);
	}


}
