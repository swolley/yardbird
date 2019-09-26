<img src="images/yardbird-icon-64x64.png" align="right"/>

# yardBird
**yardBird** is a wrapper for multiple types of databases (currently supported are all PDO drivers, Mysqli, OCI8, MongoDB). The library exposes common methods for crud functions and a parser class to translate sql queries to mongodb library syntax.
The project is still in progress and not totally tested.

## requirements
**yardBird** requires php mongodb driver and mongodb/mongodb library if you want to connect to MongoDB.
* pecl install mongodb
* composer install mongodb/mongodb

### initialization
```php
<?php

use Swolley\YardBird\Connection;

$options = [
  'driver' => 'drivername',
  'host' => 'dbhost',
  'port' => 3306,
  'dbName' => 'dbname',
  'user' => 'dbusername',
  'password' => 'dbuserpassword',
  'charset' => 'utf8',
  'sid' => 'sid for oracle connection',
  'serviceName' => 'service name for oracle connection'
];

$connection = (new Connection)($options);
```
### Basic Usage
```php
/**
* with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
* @param  string		$table						table name
* @param  array 		$params 					assoc array with columns'name 
* @param  array 		$where  					string query part or assoc array with placeholder's name and relative values. Logical separator between elements will be AND
* @param  array 		$join 						joins array
* @param  int 			$fetchMode  				(optional) PDO fetch mode. default = associative array
* @param  int|string	$fetchModeParam 			(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
* @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
* @return mixed	response array or error message
*/
$connection->select($table, $columns = [], $where = [], $join = [], $orderBy = [], $limit = null, $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, $fetchPropsLateParams = []);

/**
* execute insert query
* @param   string  			$table		table name
* @param   array|object		$params		assoc array with placeholder's name and relative values
* @param   boolean			$ignore		performes an 'insert ignore' query
* @return  int|string|bool	new row id if key is autoincremental or boolean
*/
$connection->insert($table, $params, $ignore = false);

/**
* execute update query. Where is required, no massive update permitted
* @param  string		$table		table name
* @param  array|object	$params		assoc array with placeholder's name and relative values
* @param  string|array	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
* @return bool|string	correct query execution confirm as boolean or error message
*/
$connection->update($table, $params, $where = null);

/**
* execute delete query. Where is required, no massive delete permitted
* @param  string		$table		table name
* @param  string|array	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
* @param  array			$params		assoc array with placeholder's name and relative values for where condition
* @return bool|string	correct query execution confirm as boolean or error message
*/
$connection->delete($table, $where = null, $params);

/**
* execute procedure call.
* @param  string		$table						procedure name
* @param  array			$inParams					array of input parameters
* @param  array			$outParams					array of output parameters
* @param  int			$fetchMode					(optional) PDO fetch mode. default = associative array
* @param  int|string	$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
* @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
* @return array|string	response array or error message
*/
$connection->procedure($name, $inParams = [], $outParams = [], $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, $fetchPropsLateParams = []);

/**
* execute inline or complex queries query
* @param  string		$query						query text with placeholders
* @param  array|object	$params						assoc array with placeholder's name and relative values
* @param  int			$fetchMode					(optional) PDO fetch mode. default = associative array
* @param  int|string	$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
* @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
* @return array|string	response array or error message
*/
$conncetion->sql($query, $params = [], $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, $fetchPropsLateParams = []);
```
