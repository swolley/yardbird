<?php
namespace Swolley\Database;

use Swolley\Database\Interfaces\IConnectable;
use Swolley\Database\Drivers\MongoExtended;
use Swolley\Database\Drivers\OCIExtended;
use Swolley\Database\Drivers\PDOExtended;
use Swolley\Database\Drivers\MySqliExtended;
//use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class DBFactory
{
	const FETCH_ASSOC = 2;
	const FETCH_OBJ = 5;
	const FETCH_COLUMN = 7;
	const FETCH_CLASS = 8;
	const FETCH_PROPS_LATE = 1048576;

	/**
	 * @param	array	$connectionParameters	connection parameters
	 * @param	boolean	$debugMode				debug mode
	 * @return	IConnectable	driver superclass
	 */
	public function __invoke(array $connectionParameters, bool $debugMode = false): IConnectable
	{
		if (!isset($connectionParameters, $connectionParameters['driver']) || empty($connectionParameters)) {
			throw new BadMethodCallException("Connection parameters are required");
		}

		switch (self::checkExtension($connectionParameters['driver'])) {
			case 'mongodb':
				return new MongoExtended($connectionParameters, $debugMode);
			case 'oci8':
				return new OCIExtended($connectionParameters, $debugMode);
			case 'pdo':
				return new PDOExtended($connectionParameters, $debugMode);
			case 'mysqli':
				return new MySqliExtended($connectionParameters, $debugMode);
			default:
				throw new \Exception('Extension not supported with current php configuration', 500);
		}
	}

	/**
	 * @param	string		$driver	requested type of connection
	 * @return	string|null			driver or null if no driver compatible
	 */
	private static function checkExtension(string $driver): ?string
	{
		if (empty($driver)) {
			throw new BadMethodCallException('No driver specified');
		}

		switch ($driver) {
			case 'mongo':
			case 'mongodb':
				return extension_loaded('mongodb') ? 'mongodb' : null;
			case 'oci':
				if(extension_loaded('pdo')) return 'pdo';	//correctly inside if no pdo => tries oci8
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
				return extension_loaded('pdo') ? 'pdo' : ($driver === 'mysql' && extension_loaded('mysqli') ? 'mysqli' : null);
			case 'mysqli': 
				return extension_loaded('mysqli') ? 'mysqli' : null;
			default:
				return null;
		}
	}
}
