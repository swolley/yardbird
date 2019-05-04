<?php
namespace Swolley\Database;

use Swolley\Database\Interfaces\IConnectable;
use Swolley\Database\Drivers\MongoExtended;
use Swolley\Database\Drivers\OCIExtended;
use Swolley\Database\Drivers\PDOExtended;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class DBFactory
{
	const FETCH_ASSOC = 2;
	const FETCH_OBJ = 5;
	const FETCH_COLUMN = 7;
	const FETCH_CLASS = 8;
	const GETCH_PROPS_LATE = 1048576;

	public function __invoke(array $connectionParameters)
	{
		if (!isset($connectionParameters, $connectionParameters['driver']) || empty($connectionParameters)) {
			throw new BadMethodCallException("Connection parameters are required");
		}
			
		if (!self::checkExtension($connectionParameters['driver'])) {
			throw new \Exception('Extension not supported with current php configuration', 500);
		}

		switch ($connectionParameters['driver']) {
			case 'mongodb':
				return new MongoExtended($connectionParameters);
				break;
			case 'oci8':
				return new OCIExtended($connectionParameters);
			default:
				//pdo handles unsupported classes case
				return new PDOExtended($connectionParameters);
		}
	}

	private static function checkExtension(string $driver): bool
	{
		if (empty($driver)) {
			throw new BadMethodCallException('No driver specified');
		}

		$extension_name = null;
		switch ($driver) {
			case 'mongodb':
				$extension_name = 'mongodb';
				break;
			case 'oci8':
				$extension_name = 'oci8';
				break;
			case 'cubrid':
			case 'dblib':
			case 'firebird':
			case 'ibm':
			case 'informix':
			case 'mysql':
			case 'oci':
			case 'odbc':
			case 'pgsql':
			case 'sqlite':
			case 'sqlsrv':
			case '4d':
				$extension_name = 'pdo';
				break;
		}

		return !is_null($extension_name) ? extension_loaded($extension_name) : false;
	}
}
