<?php

namespace nl\naturalis\medialib\publisher\exception;

/**
 * An {@code Exception} indicating that a media record references a file
 * that no longer actually exists on the file system.
 * 
 * @author ayco_holleman
 *
 */
class StaleRecordException extends \Exception {
	const SOURCE_FILE = 'source_file';
	const MASTER_FILE = 'master_file';
	private $_record;
	private $_column;


	/**
	 * Instantiate a new {@code StaleRecordException}.
	 * 
	 * @param object $record A record from the media table that references a non-existent file.
	 * in one of its columns. The record must have been fetched using {@code PDO::FETCH_OBJ}.
	 * @param string $column The column with the corrupt reference. Choose between
	 * {@code StaleRecordException::SOURCE_FILE} and {@code StaleRecordException::MASTER_FILE}.
	 */
	public function __construct($record, $column)
	{
		$format = 'Stale database record. "%1$s". %2$s no longer exists. Record will be deleted. %2$s: %3$s';
		if($column === self::SOURCE_FILE) {
			parent::__construct(sprintf($format, $record->regno, 'Source file', $record->source_file));
		}
		else if($column === self::MASTER_FILE) {
			parent::__construct(sprintf($format, $record->regno, 'Master file', $record->master_file));
		}
		else {
			parent::__construct('Program error');
		}
		$this->_record = $record;
		$this->_column = $column;
	}


	public function getRecord()
	{
		return $this->_record;
	}


	public function getColumn()
	{
		return $this->_column;
	}

}