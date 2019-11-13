[![Codacy Badge](https://api.codacy.com/project/badge/Grade/50d78b0ce43246178e002afc66dd6706)](https://www.codacy.com/manual/swolley/yardbird?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=swolley/yardbird&amp;utm_campaign=Badge_Grade)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

<img src="images/yardbird-icon-64x64.png" align="right"/>

# yarDBird
**yarDBird** is a wrapper for multiple types of databases (currently supported are all PDO drivers, Mysqli, OCI8, MongoDB). The library exposes common methods for crud functions and a parser class to translate sql queries to mongodb library syntax.
The project is still in progress and not totally tested.

## Requirements
**yarDBird** optionally requires php mongodb driver and mongodb/mongodb library if you want to connect to MongoDB.
* composer (composer-install)
* pecl install mongodb
* composer install mongodb/mongodb

### Initialization
Creating a connection

```php
<?php
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
//create connection wrapper
$conn = new Swolley\YardBird\Connection($db_params);
//execute queries
$stmt = $conn->select(/*...*/);
$stmt = $conn->insert(/*...*/);
$stmt = $conn->update(/*...*/);
$stmt = $conn->delete(/*...*/);
$stmt = $conn->procedure(/*...*/);
$stmt = $conn->sql(/*...*/);
//results
$stmt->ok();
$stmt->count();
$stmt->insertedId();
$stmt->fetch(/*...*/);

```
### Basic Usage
Main apis for crud operations

```php
/**
* with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
* @param  string		$table						table name
* @param  array 		$columns 					array with columns'name or column => alias
* @param  array 		$where  					string query part or assoc array with placeholder's name and relative values. Logical separator between elements will be AND
* @param  array 		$join 						joins array
* @param  array 		$orderBy					order by array
* @return Swolley\YardBird\Result	statement wrapper

* SELECT id as code, name FROM users WHERE surname='smith' AND 'email' = 'my@mail.com' ORDER BY name ASC
* $conn->select('users', ['id' => 'code', 'name'], ['surname' => 'smith', 'email' => 'my@mail.com'], ['name' => 1]);	//binded
*
* SELECT * FROM users
* $conn->select('users');
*/
Connection::select($table, $columns = [], $where = [], $join = [], $orderBy = [], $limit = null): Swolley\YardBird\Result;

/**
* execute insert query
* @param   string  			$table		table name
* @param   array|object		$params		assoc array with placeholder's name and relative values
* @param   boolean			$ignore		performes an 'insert ignore' query
* @return  Swolley\YardBird\Result	statement wrapper

* INSERT(name) INTO users VALUES('mark')
* $conn->insert('users', ['name' => 'mark']);	//binded
*/
Connection::insert($table, $params, $ignore = false): Swolley\YardBird\Result;

/**
* execute update query. Where is required, no massive update permitted
* @param  string		$table		table name
* @param  array|object	$params		assoc array with placeholder's name and relative values
* @param  string|array	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
* @return Swolley\YardBird\Result	statement wrapper

* UPDATE users SET name='mark' WHERE name='paul'
* $conn->update('users', ['name' => 'mark'], "name='paul'");	//where not binded
* $conn->update('users', ['name' => 'mark'], "name=':nameToFind'", ['nameToFind' => 'paul']);	//binded
*/
Connection::update($table, $params, $where = null): Swolley\YardBird\Result;

/**
* execute delete query. Where is required, no massive delete permitted
* @param  string		$table		table name
* @param  string|array	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
* @param  array			$params		assoc array with placeholder's name and relative values for where condition
* @return Swolley\YardBird\Result	statement wrapper

* DELETE FROM users WHERE name='paul'
* $conn->delete('users', "name='paul'");	//not binded
* $conn->delete('users', "name=':nameToFind'", ['nameToFind' => 'paul']);	//binded
*/
Connection::delete($table, $where = null, $params): Swolley\YardBird\Result;

/**
* execute procedure call.
* @param  string		$table						procedure name
* @param  array			$inParams					array of input parameters
* @param  array			$outParams					array of output parameters
* @return array|Swolley\YardBird\Result		out param's array or statement wrapper

* CALL myprocedure (1, 'adrian')
* $conn->procedure('myprocedure', ['id' => 1, name => 'adrian']);	//binded
*/
Connection::procedure($name, $inParams = [], $outParams = []);

/**
* execute inline or complex queries query
* @param  string		$query						query text with placeholders
* @param  array|object	$params						assoc array with placeholder's name and relative values
* @return Swolley\YardBird\Result	statement wrapper
* ex.
* SELECT * FROM users WHERE surname='smith'
* $conn->sql("SELECT * FROM users WHERE surname='smith'");	//not binded
* $conn->sql('SELECT * FROM users WHERE surname=:toFind', ['toFind' => 'smith']);	//binded
*/
Connection::sql($query, $params = []): Swolley\YardBird\Result;

/**
 * fetch and parse query results
 * @param	int			$fetchMode				(optional) fetch mode. default ASSOCIATIVE ARRAY
 * @param	int|string	$fetchModeParam			(optional) fetch mode param if fetch mode is class or column
 * @param	array		$fetchPropsLateParams	(optional) constructor params if fetch mode has FETCH_PROPS_LATE option
 * @return	array		fetched and parsed data
 */
Result::fetch(int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

/**
 * checks if query result is positive
 * @return	bool	query outcome
 */
Result::ok(): bool;

/**
 * counts result extracted or affected rows
 * @return	int	num rows
 */
Result::count(): int;

/**
 * get last inserted id if table has an autoincrement primary key
 * @return	int|null	last inserted id
 */
Result::insertedId();
```
### Connection pool
Pools can organize all connections in a single object

```php
<?php
use Swolley\YardBird\Pool;

$pool = new Pool;
$pool->add('my_mysql_conn', [ 'driver' => 'mysql',	'host' => '127.0.0.1',	'dbName' => 'dbname1',	'user' => 'dbusername',	'password' => 'dbuserpassword' ])
	->add('oracle_db', [ 'driver' => 'oci', 	'host' => '127.0.0.1',	'dbName' => 'dbname2',	'user' => 'dbusername',	'password' => 'dbuserpassword',	'serviceName' => 'mysn' ])
	->add('another_to_mongo', [ 'driver' => 'mongodb', 'host' => '127.0.0.1',	'dbName' => 'dbname3',	'user' => 'dbusername',	'password' => 'dbuserpassword' ]);
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
$mongo_conn = new Swolley\YardBird\Connection([ 'driver' => 'mongodb', 'host' => 'dbhost', 'dbName' => 'dbname', 'user' => 'dbusername', 'password' => 'dbuserpassword' ]);
//using sql method the inbuilt QueryBuilder parses automatically sql syntax to mongoDB query schema
$result = $mongo_conn->sql(/*sql syntax query, ex. "SELECT name FROM users"*/);
```

### Class Builder
Currently a work in progress

```php
<?php
$conn = new Swolley\YardBird\Connection([ /*... connection params */ ]);

/**
 * reads all tables and columns info and creates classes. Class's properties are generated as private with relative getters and setters containig validations depending on db columns' types
 * @param	IConnectable	$conn 			db connection instance
 * @param	bool			$prettyNames	(optional) respect db naming convention or prettify tables and properties names. Default is true
 * @param	string|null		$classPath		(optional) write generated class definitions to filesystem or evaluate at runtime if null
 */
Swolley\YardBird\Utils\ClassBuilder::mapDB($conn);

/*
CREATE TABLE `my_user` (
  `user_id` int(10) unsigned NOT NULL,
  `user_name` varchar(50),
  `password` char(64) NOT NULL
);

final class MyUser extends Swolley\YardBird\Models\AbstractModel 
{ 
	//user_id int(10) unsigned NOT NULL
	private $userId;
	public function getUserId() { return $this->userId; }
	public function setUserId(int $userId  ) { if(strlen((string)$userId) <= 10 && $userId > 0) $this->userId = $userId; }

	//user_name varchar(50)
	private $userName;
	public function getUserName() { return $this->userName; }
	public function setUserName(?string $userName = null ) { if(strlen($userName) <= 50 || userName === null) $this->userName = $userName; }

	//password char(64) NOT NULL
	private $password;
	public function getPassword() { return $this->password; }
	public function setPassword(string $password  ) { if(strlen($password) === 64) $this->password = $password; }
}
*/
```