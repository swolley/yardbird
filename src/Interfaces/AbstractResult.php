<?php
namespace Swolley\YardBird\Interfaces;

use Swolley\YardBird\Connection;

abstract class AbstractResult {
	protected $_sth;
	protected $_insertedId;

	public function __construct($stmt, int $insertedId = null)
	{
		$this->_sth = $stmt;
		$this->_insertedId = $insertedId;
	}

	/**
	 * fetch and parse query results
	 * @param	int			$fetchMode				(optional) fetch mode. default ASSOCIATIVE ARRAY
	 * @param	int|string	$fetchModeParam			(optional) fetch mode param if fetch mode is class or column
	 * @param	array		$fetchPropsLateParams	(optional) constructor params if fetch mode has FETCH_PROPS_LATE option
	 * @return	array		fetched and parsed data
	 */
	abstract function fetch(int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * affected rows number
	 * @return int	num rows
	 */
	abstract function count(): int;

	/**
	 * if in transaction new inserted id
	 * @return	int|null	last inserted autoincrement id
	 */
	public function insertedId()
	{
		return $this->_insertedId;
	}

	public function ok(): bool
	{
		return $this->count() > 0;
	}
}