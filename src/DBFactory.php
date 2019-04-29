<?php
namespace Swolley\Database;

use PHPUnit\Framework\MockObject\BadMethodCallException;

//require_once "PDOExtended.php";
//require_once "OCIExtended.php";

final class DBFactory
{
	public function __invoke(array $connectionParameters)
	{
		if (!isset($connectionParameters, $connectionParameters['driver']) || empty($connectionParameters)) {
			throw new \BadMethodCallException("Connection parameters are required");
		}
		try {
			if (!self::checkExtension($connectionParameters['driver'])) {
				throw new \Exception('Extension not supported with current php configuration', 500);
			}

			switch ($connectionParameters['driver']) {
				case 'mongodb':
					//return new MongoExtended($connectionParameters);
					break;
				case 'oci8':
					return new OCIExtended($connectionParameters);
				default:
					//pdo handles unsupported classes case
					return new PDOExtended($connectionParameters);
			}
		} catch (\Exception $e) {
			throw new \Exception(error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error', 500);
		}
	}

	private static function checkExtension(string $driver): bool
	{
		if (empty($driver)) {
			throw new \BadMethodCallException('No driver specified');
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
