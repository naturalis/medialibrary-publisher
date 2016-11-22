<?php

namespace nl\naturalis\medialib\util\context;

use nl\naturalis\medialib\util\logging\BasicMonologHandler;
use nl\naturalis\medialib\util\logging\LoggerFactory;
use nl\naturalis\medialib\util\Config;
use \PDO;
use Monolog\Logger;

/**
 * A {@code Context} object sets up and provides shared services (logging) and
 * resources (pdo) to the classes that participating in a medialib process. 
 * 
 * @author ayco_holleman
 *        
 */
class Context {
	
	/**
	 *
	 * @var Logger
	 */
	private static $_logger;
	/**
	 *
	 * @var Config
	 */
	private $_config;
	/**
	 *
	 * @var LoggerFactory
	 */
	private $_loggerFactory;
	/**
	 *
	 * @var BasicMonologHandler
	 */
	private $_logHandler;
	private $_logFile;
	/**
	 *
	 * @var PDO
	 */
	private $_pdo;
	/**
	 *
	 * @var array
	 */
	private $_props;


	public function __construct(Config $config)
	{
		$this->_config = $config;
	}


	/**
	 *
	 * @return \Monolog\Logger
	 */
	public function getLogger($name)
	{
		if($this->_loggerFactory === null) {
			throw new \Exception('Logging not initialized yet. Call Context::initializeLogging() first');
		}
		return $this->_loggerFactory->getLogger($name);
	}


	public function getLogFile()
	{
		if($this->_loggerFactory === null) {
			throw new \Exception('Logging not initialized yet. Call Context::initializeLogging() first');
		}
		return $this->_logFile;
	}


	/**
	 *
	 * @return PDO
	 */
	public function getSharedPDO()
	{
		if($this->_pdo === null) {
			$host = $this->_config->db0->host;
			if($host === null) {
				$host = 'localhost';
			}
			$user = $this->_config->db0->user;
			$password = $this->_config->db0->password;
			$db = $this->_config->db0->dbname;
			$dsn = "mysql:host={$host};dbname={$db}";
			try {
				$this->_pdo = new PDO($dsn, $user, $password, array(
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
				));
			}
			catch(\PDOException $e) {
				self::$_logger->addError('Could not connect to database - ' . $e->getMessage());
				throw $e;
			}
		}
		return $this->_pdo;
	}


	public function shutdown()
	{
		if($this->_logHandler !== null) {
			$this->_logHandler->close();
			$this->_logHandler = null;
		}
		$this->_pdo = null;
	}


	/**
	 *
	 * @return nl\naturalis\medialib\util\Config
	 */
	public function getConfig()
	{
		return $this->_config;
	}


	public function setProperty($name, $val)
	{
		$this->_props[$name] = $val;
	}


	public function getRequiredProperty($name)
	{
		if(!isset($this->_props[$name])) {
			throw new \Exception('No such property: ' . $name);
		}
		return $this->_props[$name];
	}


	public function initializeLogging($logFile)
	{
		$this->_logFile = $logFile;
		$stdout = $this->_config->getBoolean('logging.stdout');
		$level = self::getMonologLoggingLevel($this->_config->logging->level);
		$this->_logHandler = new BasicMonologHandler();
		$this->_logHandler->setStdout($stdout);
		$this->_logHandler->setLogFile($this->_logFile);
		$this->_logHandler->setLevel($level);
		$this->_loggerFactory = new LoggerFactory($this->_logHandler);
		self::$_logger = $this->_loggerFactory->getLogger(__CLASS__);
		self::$_logger->addDebug('Log file: ' . $logFile);
	}


	private static function getMonologLoggingLevel($level)
	{
		switch ($level) {
			case 'DEBUG' :
				return Logger::DEBUG;
			case 'INFO' :
				return Logger::INFO;
			case 'WARNING' :
				return Logger::WARNING;
			case 'ERROR' :
				return Logger::ERROR;
		}
		throw new \Exception("Invalid logging level: \"$level\". Valid values: DEBUG, INFO, WARNING, ERROR.");
	}

}