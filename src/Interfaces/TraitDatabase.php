<?php
declare(strict_types=1);

namespace Swolley\YardBird\Interfaces;

use Swolley\YardBird\Utils\Utils;

trait TraitDatabase
{
	/**
	 * @var	string	$_dbName	db name
	 * @var	string	$_driver	driver name
	 * @var	string	$_host		host ip
	 * @var	string	$_hash		connection hash id
	 * @var	boolean	$_debugMode	enables debug mode
	 */
	protected $_dbName = '';
	protected $_driver = '';
	protected $_host = '';
	protected $_hash = '';
	protected $_debugMode = false;

	protected function setInfo(array $connectionParams, bool $debugMode)
	{
		switch($connectionParams['driver']) {
			case 'oci': 
				$this->_driver = 'oracle';
				break;
			case 'pgsql':
				$this->_driver = 'postgres';
				break;
			case 'mssql':
				$this->_driver = 'sqlserver';
				break;
			case 'mysqli':
				$this->_driver = 'mysql';
				break;
			default:
				$this->_driver = $connectionParams['driver'];
		}
		$this->_host = $connectionParams['host'];
		$this->_dbName = $connectionParams['dbName'];
		$this->_debugMode = $debugMode;
		$this->_hash = Utils::hash($connectionParams);
	}

	public function getInfo(): array
	{
		return [ 'driver' => $this->getType(), 'host' => $this->getHost(), 'dbName' => $this->getDbName() ];
	}

	/**
	 * driver getter
	 * @return	string	driver
	 */
	public function getType(): string
	{
		return $this->_driver;
	}

	/**
	 * hash getter
	 * @return	string	hash
	 */
	public function getHash(): string
	{
		return $this->_hash;
	}

	/**
	 * host getter
	 * @return	string	host
	 */
	public function getHost(): string
	{
		return $this->_host;
	}

	/**
	 * dbName getter
	 * @return	string	host
	 */
	public function getDbName(): string
	{
		return $this->_dbName;
	}
}