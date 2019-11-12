<?php
namespace Swolley\YardBird\Results;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\AbstractResult;

class OciResult extends AbstractResult
{
	public function __construct(resource $sth, int $insertedId = null)
	{
		parent::__construct($sth, $insertedId);
	}

	public function fetch(int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$response = [];
		if ($fetchMode === Connection::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = oci_fetch_row($this->_sth)[$fetchModeParam] !== false) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & Connection::FETCH_CLASS && is_string($fetchModeParam)) {
			while ($row = oci_fetch_assoc($this->_sth) !== false) {
				array_push($response, new $fetchModeParam(...$row));
			}
		} else {
			while ($row = oci_fetch_assoc($this->_sth) !== false) {
				array_push($response, $row);
			}
		}

		return $response;
	}

	public function count(): int
	{
		return oci_num_rows($this->_sth);
	}
}