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

		$extension_name = self::checkExtension($connectionParameters['driver']);
		if (is_null($extension_name)) {
			throw new \Exception('Extension not supported with current php configuration', 500);
		}

		switch ($extension_name) {
			case 'mongodb':
				return new MongoExtended($connectionParameters);
			case 'oci8':
				return new OCIExtended($connectionParameters);
			case 'pdo':
				return new PDOExtended($connectionParameters);
			case 'mysqli':
				return new MySqliExtended($connectionParameters);
		}
	}

	/**
	 * @param	string	driver
	 */
	private static function checkExtension(string $driver): ?string
	{
		if (empty($driver)) {
			throw new BadMethodCallException('No driver specified');
		}

		switch ($driver) {
			case 'mongodb':
				return extension_loaded('mongodb') ? 'mongodb' : null;
			case 'oci':
				if(extension_loaded('pdo')){
					return 'pdo';	//correctly inside if. no pdo => tries oci8
				}
			case 'oci8':
				return extension_loaded('oci8') ? 'oci8' : null;
			case 'cubrid':
			case 'dblib':
			case 'firebird':
			case 'ibm':
			case 'informix':
			case 'odbc':
			case 'pgsql':
			case 'sqlite':
			case 'sqlsrv':
			case '4d':
			case 'mysql':
				return extension_loaded('pdo') ? 'pdo' : $driver === 'mysql' && extension_loaded('mysqli') ? 'mysqli' : null;
			default:
				return null;
		}
	}
}
