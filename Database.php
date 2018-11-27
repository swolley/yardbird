<?php
namespace Database;

class Database {
	public function __invoke(string $type, string $host, int $port, string $user, string $pass, string $dbName, string $charset = 'UTF8') {
		
		try{
			switch($type) {
				case 'mongodb':
					return new MongoExtended($host, $port, $user, $pass, $dbName);
					break;
				default:
					return new PDOExtended($type, $host, $port, $user, $pass, $dbName, $charset);
				
			}
			
		} catch(\Exception $e){
			throw new Exception(error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error', 500);
		}
	}
}
