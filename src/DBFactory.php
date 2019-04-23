<?php
namespace Swolley\Database;

//require_once "PDOExtended.php";
//require_once "OCIExtended.php";

final class DBFactory {
	public function __invoke(array $connectionParameters) {
		if(!isset($connectionParameters, $connectionParameters['driver']) || empty($connectionParameters)) {
			throw new \BadMethodCallException("Connection parameters are required");
		}
		try{
			if(!self::checkExtension($connectionParameters['driver'])) {
				throw new Exception('Extension not supported with current php configuration', 500);
			}

			switch($connectionParameters['driver']) {
				case 'mongodb':
					//return new MongoExtended($connectionParameters);
					break;
				case 'oci8':
					return new OCIExtended($connectionParameters);
				default:
					//pdo handles unsupported classes case
					return new PDOExtended($connectionParameters);
			}
			
		} catch(\Exception $e){
			throw new Exception(error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error', 500);
		}
	}

	private static function checkExtension(string $driver): bool
	{
		$extension_name = null;
		switch($driver) {
			case 'mongodb':
				$extension_name = 'mongodb';
				break;
			case 'oci8':
				$extension_name = 'oci8';
				break;
			default:
				$extension_name = 'pdo';
				break;
		}
		
		return extension_loaded($extension_name);
	}
}
