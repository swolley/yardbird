<?php
namespace Swolley\YardBird\Results;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\AbstractResult;

class PdoResult extends AbstractResult
{
	public function __construct(\PDOStatement $stmt, int $insertedId = null)
	{
		parent::__construct($stmt, $insertedId);
	}

	public function fetch(int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		return ($fetchMode === Connection::FETCH_COLUMN && is_int($fetchModeParam)) || ($fetchMode & Connection::FETCH_CLASS && is_string($fetchModeParam))
			? $fetchMode & Connection::FETCH_PROPS_LATE ? $this->_sth->fetchAll($fetchMode, $fetchModeParam, $fetchPropsLateParams) : $this->_sth->fetchAll($fetchMode, $fetchModeParam)
			: $this->_sth->fetchAll($fetchMode);
	}

	public function count(): int
	{
		return $this->_sth->rowCount();
	}
}