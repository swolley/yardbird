<?php
namespace Swolley\YardBird\Results;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\AbstractResult;

class MysqliResult extends AbstractResult
{
	public function __construct(\mysqli_stmt $stmt, string $queryType = null, int $insertedId = null)
	{
		parent::__construct($stmt, $insertedId);
		$this->_queryType = $queryType;
	}

	public function fetch(int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$meta = $this->_sth->result_metadata();
		if(!$meta) return [];

		$response = [];
		if ($fetchMode === Connection::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = $meta->fetch_field_direct($fetchModeParam)) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & Connection::FETCH_CLASS && is_string($fetchModeParam)) {
			while ($row = !empty($fetchPropsLateParams) ? $meta->fetch_object($fetchModeParam, $fetchPropsLateParams) : $meta->fetch_object($fetchModeParam)) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & Connection::FETCH_OBJ) {
			while ($row = $meta->fetch_object()) {
				array_push($response, $row);
			}
		} else {
			$response = $meta->fetch_all(MYSQLI_ASSOC);
		}

		return $response;
	}

	public function count(): int
	{
		switch($this->_queryType) {
			case 'select':
				return $this->_sth->num_rows;
			default: 
				if($this->_sth->affected_rows !== -1) throw new QueryException('An error occured during query');
				return $this->_sth->affected_rows;
		}
	}
}