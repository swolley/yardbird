[![Codacy Badge](https://api.codacy.com/project/badge/Grade/50d78b0ce43246178e002afc66dd6706)](https://www.codacy.com/manual/swolley/yardbird?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=swolley/yardbird&amp;utm_campaign=Badge_Grade)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

<img src="images/yardbird-icon-64x64.png" align="right"/>

# yarDBird
**yarDBird** is a wrapper for multiple types of databases (currently supported are all PDO drivers, Mysqli, OCI8, MongoDB). The library exposes common methods for crud functions and a parser class to translate sql queries to mongodb library syntax.
The project is still in progress and not totally tested.

## requirements
**yarDBird** requires php mongodb driver and mongodb/mongodb library if you want to connect to MongoDB.
* pecl install mongodb
* composer install mongodb/mongodb

### initialization
```php
<?php
use Swolley\YardBird\Connection;

$db_params = [
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

$conn = (new Connection)($db_params);
$conn->select(/*...*/);
$conn->insert(/*...*/);
$conn->update(/*...*/);
$conn->delete(/*...*/);
$conn->procedure(/*...*/);
$conn->sql(/*...*/);

```
### Basic Usage
```php
/**
* with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
* @param  string		$table						table name
* @param  array 		$columns 					array with columns'name or column => alias
* @param  array 		$where  					string query part or assoc array with placeholder's name and relative values. Logical separator between elements will be AND
* @param  array 		$join 						joins array
* @param  array 		$orderBy					order by array
* @param  int 			$fetchMode  				(optional) PDO fetch mode. default = associative array
* @param  int|string	$fetchModeParam 			(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
* @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
* @return mixed	response array or error message

* SELECT id as code, name FROM users WHERE surname='smith' AND 'email' = 'my@mail.com' ORDER BY name ASC
* $conn->select('users', ['id' => 'code', 'name'], ['surname' => 'smith', 'email' => 'my@mail.com'], ['name' => 1]);	//binded
*
* SELECT * FROM users
* $conn->select('users');
*/
$conn->select($table, $columns = [], $where = [], $join = [], $orderBy = [], $limit = null, $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, $fetchPropsLateParams = []);

/**
* execute insert query
* @param   string  			$table		table name
* @param   array|object		$params		assoc array with placeholder's name and relative values
* @param   boolean			$ignore		performes an 'insert ignore' query
* @return  int|string|bool	new row id if key is autoincremental or boolean

* INSERT(name) INTO users VALUES('mark')
* $conn->insert('users', ['name' => 'mark']);	//binded
*/
$conn->insert($table, $params, $ignore = false);

/**
* execute update query. Where is required, no massive update permitted
* @param  string		$table		table name
* @param  array|object	$params		assoc array with placeholder's name and relative values
* @param  string|array	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
* @return bool|string	correct query execution confirm as boolean or error message

* UPDATE users SET name='mark' WHERE name='paul'
* $conn->update('users', ['name' => 'mark'], "name='paul'");	//where not binded
* $conn->update('users', ['name' => 'mark'], "name=':nameToFind'", ['nameToFind' => 'paul']);	//binded
*/
$conn->update($table, $params, $where = null);

/**
* execute delete query. Where is required, no massive delete permitted
* @param  string		$table		table name
* @param  string|array	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
* @param  array			$params		assoc array with placeholder's name and relative values for where condition
* @return bool|string	correct query execution confirm as boolean or error message

* DELETE FROM users WHERE name='paul'
* $conn->delete('users', "name='paul'");	//not binded
* $conn->delete('users', "name=':nameToFind'", ['nameToFind' => 'paul']);	//binded
*/
$conn->delete($table, $where = null, $params);

/**
* execute procedure call.
* @param  string		$table						procedure name
* @param  array			$inParams					array of input parameters
* @param  array			$outParams					array of output parameters
* @param  int			$fetchMode					(optional) PDO fetch mode. default = associative array
* @param  int|string	$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
* @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
* @return array|string	response array or error message

* CALL myprocedure (1, 'adrian')
* $conn->procedure('myprocedure', ['id' => 1, name => 'adrian']);	//binded
*/
$conn->procedure($name, $inParams = [], $outParams = [], $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, $fetchPropsLateParams = []);

/**
* execute inline or complex queries query
* @param  string		$query						query text with placeholders
* @param  array|object	$params						assoc array with placeholder's name and relative values
* @param  int			$fetchMode					(optional) PDO fetch mode. default = associative array
* @param  int|string	$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
* @param  int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
* @return array|string	response array or error message
* ex.
* SELECT * FROM users WHERE surname='smith'
* $conn->sql("SELECT * FROM users WHERE surname='smith'");	//not binded
* $conn->sql('SELECT * FROM users WHERE surname=:toFind', ['toFind' => 'smith']);	//binded
*/
$conn->sql($query, $params = [], $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, $fetchPropsLateParams = []);
```
### Connection pool
```php
<?php
use Swolley\YardBird\Pool;

$db1 = [ 'driver' => 'mysql',	'host' => '127.0.0.1',	'dbName' => 'dbname1',	'user' => 'dbusername',	'password' => 'dbuserpassword' ];
$db2 = [ 'driver' => 'oci', 	'host' => '127.0.0.1',	'dbName' => 'dbname2',	'user' => 'dbusername',	'password' => 'dbuserpassword',	'serviceName' => 'mysn' ];
$db3 = [ 'driver' => 'mongodb', 'host' => '127.0.0.1',	'dbName' => 'dbname3',	'user' => 'dbusername',	'password' => 'dbuserpassword' ];

$pool = new Pool;
$pool
	->add('my_mysql_conn', $db1)
	->add('oracle_db', $db2)
	->add('another_to_mongo', $db3);
$list = $pool->list();
/*
	result:
	[ 
		'my_mysql_conn' => [ 'driver' => 'mysql', 'host' => '127.0.0.1', 'dbName' => 'dbname1' ],
		'oracle_db' => [ 'driver' => 'oracle', 'host' => '127.0.0.1', 'dbName' => 'dbname2' ],
		'another_to_mongo' => [ 'driver' => 'mongodb', 'host' => '127.0.0.1', 'dbName' => 'dbname3' ]
	]
*/

//reads all rows from 'my_mysql_conn.table_name'
foreach($pool->my_mysql_conn->select('table_name', ['field1', 'field2']) as $row) {
	//insert into 'oracle_db.table_name'
	$pool->oracle_db->insert('table_name', [ 'field3' => $row['field1']);
	//update 'another_to_mongo.table_name' where 'field5' = 'field2'
	$pool->another_to_mongo->update('table_name', [ 'field4' => $row['field1'], ['field5' => $row['field2']]);
}
```

### Query Builder
The function is totally experimental and it's a work in progress

```php
<?php
use Swolley\YardBird\Connection;

$db_params = [ 'driver' => 'mongodb', 'host' => 'dbhost', 'dbName' => 'dbname', 'user' => 'dbusername', 'password' => 'dbuserpassword' ];
$mongo_conn = (new Connection)($db_params);
//using sql method the inbuilt QueryBuilder parses automatically sql syntax to mongoDB query schema
$result = $mongo_conn->sql(/*sql syntax query, ex. "SELECT name FROM users"*/);
```