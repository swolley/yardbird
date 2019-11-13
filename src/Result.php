<?php
namespace Swolley\YardBird;

use Swolley\YardBird\Exceptions\BadMethodCallException;

final class Result
{
	private $_driverResult;

	public function __construct($stmt, string $queryType, int $insertedId = null)
	{
		if(is_resource($stmt) && get_resource_type($stmt) === 'oci8 statement') {
			$this->_driverResult = new Results\OciResult($stmt, $insertedId);
		} elseif(is_object($stmt)) {
			switch(get_class($stmt)) {
				case 'PDOStatement':
					$this->_driverResult = new Results\PdoResult($stmt, $insertedId);
					break;
				case 'mysqli_stmt':
					$this->_driverResult = new Results\MysqliResult($stmt, $queryType, $insertedId);
					break;
				case 'Cursor':
					$this->_driverResult = new Results\MongoResult($stmt, $insertedId);
					break;
				}
		}
	}

	public function __call($method, $params)
	{
		if(method_exists($this->_driverResult, $method)) {
			return $this->_driverResult->$method(...$params);
		} else {
			throw new BadMethodCallException('Method $method not found');
		}
	}
}