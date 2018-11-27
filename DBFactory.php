<?php
namespace Extensions;

use Extensions\HttpStatusCode as Http;

class DBFactory {
	public static function connect(string $type, string $host, int $port, string $user, string $pass, string $dbName, string $charset = 'UTF8') {
		
		try{
			switch($type) {
				case 'mongodb':
					return new MongoExtended($host, $port, $user, $pass, $dbName);
					break;
				default:
					return new PDOExtended($type, $host, $port, $user, $pass, $dbName, $charset);
				
			}
			
		} catch(\Exception $e){
			throw new \Exception($e, Http::INTERNAL_SERVER_ERROR);
		}
	}
}