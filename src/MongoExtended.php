<?php
namespace Swolley\Database;

use
	\MongoDB\Client as MongoDB,
	\MongoDB\BSON as BSON,
	\MongoDB\Driver\Command as MongoCmd,
	\MongoDB\BSON\Javascript as MongoJs,
	\MongoDB\Driver\Exception as MongoException,
	\MongoLog;
use MongoDB\Exception\BadMethodCallException;
use MongoDB\Exception\UnexpectedValueException;

class MongoExtended extends MongoDB
{
	private $dbName;

	/**
	 * @param	array	$params	connection parameters
	 */
	public function __construct(array $params)
	{
		$params = self::validateParams($params);
		parent::__construct(self::constructConnectionString($params));
		$this->dbName = $params['dbName'];
	}

	public static function validateParams($params): array
	{
		//string $host, int $port, string $user, string $pass, string $dbName
		if (!isset($params['host'], $params['user'], $params['password'], $params['dbName'])) {
			throw new BadMethodCallException("host, user, password, dbName are required");
		} elseif (empty($params['host']) || empty($params['user']) || empty($params['password']) || empty($params['dbName'])) {
			throw new UnexpectedValueException("host, user, password, dbName can't be empty");
		}

		return $params;
	}

	public static function constructConnectionString(array $params, array $init_arr = []): string
	{
		return "mongodb://{$params['user']}:{$params['password']}@{$params['host']}:{$params['port']}/{$params['dbName']}";
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function query(string $query, array $params = []) {
		return self::queryParser($query, $params);
	}

	//function select(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []);
	public function select(string $collection, array $search, array $options = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		try {
			foreach ($search as &$param) {
				$param = filter_var($param);
			}

			//FIXME does options need to be filtered???
			/*foreach ($options as $key => &$param) {
				$param = filter_var($param);
			}*/

			$response = $this->{$this->dbName}->{$collection}->find($search, $options);
			switch($fetchMode) {
				case DBFactory::FETCH_ASSOC:
					$response->setTypeMap([ 'root' => 'array', 'document' => 'array', 'array' => 'array' ]);
					break;
				case DBFactory::FETCH_OBJ:
					$response->setTypeMap([ 'root' => 'object', 'document' => 'object', 'array' => 'array' ]);
					break;
				//case DBFactory::FETCH_CLASS:
				//	$response->setTypeMap([ 'root' => 'object', 'document' => $fetchModeParam, 'array' => 'array' ]);
				//	break;
				default: 
					throw new MongoException\CommandException('Can fetch only Object or Associative Array');
			}

			return $response->toArray();
		} catch (MongoException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	/**
	 * execute insert query
	 * @param   string  $collection     collection name
	 * @param   array|object   $params         assoc array with placeholder's name and relative values
	 * @param   boolean $ignore         performes an 'insert ignore' query
	 * @return  mixed                   new row id or error message
	 */
	//function insert(string $table, $params, bool $ignore = false);
	public function insert(string $collection, $params, bool $ignore = false)
	{
		$paramsType = gettype($params);
		if($paramsType !== 'array' && $paramsType !== 'object' ) {
			throw new \UnexpectedValueException('$params can be only array or object');
		}

		if($paramsType === 'object') {
			$params = (array) $params;
		}
		
		try {
			foreach ($params as &$param) {
				$param = filter_var($param);
			}

			return $this->{$this->dbName}->{$collection}
				->insertOne($params, ['ordered' => !$ignore])
				->getInsertedId()['oid'];
		} catch (MongoException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	/**
	 * execute update query. Where is required, no massive update permitted
	 * @param   string  $collection     collection name
	 * @param   array|object   $params         assoc array with placeholder's name and relative values
	 * @param   array   $where          where condition. no placeholders permitted
	 * @return  mixed                   correct query execution confirm as boolean or error message
	 */
	//function update(string $table, $params, string $where);
	public function update(string $collection, $params, array $where)
	{
		$paramsType = gettype($params);
		if($paramsType !== 'array' && $paramsType !== 'object' ) {
			throw new \UnexpectedValueException('$params can be only array or object');
		}

		if($paramsType === 'object') {
			$params = (array) $params;
		}

		try {
			foreach ($params as &$param) {
				$param = filter_var($param);
			}

			foreach ($where as &$param) {
				$param = filter_var($param);
			}

			return $this->{$this->dbName}->{$collection}
				->updateMany($where, ['$set' => $params], ['upsert' => FALSE])
				->getModifiedCount();
		} catch (MongoException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	/**
	 * execute delete query. Where is required, no massive delete permitted
	 * @param   string  $collection     collection name
	 * @param   array   $where          where condition with placeholders
	 * @return  mixed                   correct query execution confirm as boolean or error message
	 */
	//function delete(string $table, string $where, array $params);
	public function delete(string $collection, array $where)
	{
		try {
			foreach ($where as &$param) {
				$param = filter_var($param);
			}

			$result = $this->{$this->dbName}->{$collection}
				->deleteMany($where);

			return $result->getDeletedCount() ? TRUE : FALSE;
		} catch (MongoException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	/**
	 * execute stored procedure
	 * @param   string  $name           stored procedure name
	 * @param   array   $params         (optional) assoc array with paramter's names and relative values
	 * @return  mixed                   stored procedure result or error message
	 */
	public function procedure(string $name, array $params = [])
	{
		//TODO to be tested
		try {
			foreach ($params as &$param) {
				$param = filter_var($param);
			}

			$jscode = new MongoJs('return db.eval("return ' . $name . '(' . implode(array_values($params)) . ');');
			$command = new MongoCmd(['eval' => $jscode]);
			return $this->executeCommand($this->dbName, $command);
		} catch (MongoException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	private static function queryParser(string $query, array $params = []) {
		$exploded = explode(' ', $query);
		if(preg_match('/^select$/i', $exploded[0]) === 1) {
			self::parseSelect($exploded);
		}elseif(preg_match('/^insert$/i', $exploded[0]) === 1) {
			self::parseInsert($exploded);
		} elseif(preg_match('/^delete$/i', $exploded[0]) === 1) {
			self::parseDelete($exploded);
		} elseif(preg_match('/^delete$/i', $exploded[0]) === 1) {
			self::parseUpdate($exploded);
		} else {
			throw new \UnexpectedValueException('queryParser is unable to convert query');
		}
	}

	private static function parseSelect(array $query) {
		/*
		SELECT a,b FROM users	$db->users->find(array(), array("a" => 1, "b" => 1));
		SELECT * FROM users WHERE age=33	$db->users->find(array("age" => 33));
		SELECT a,b FROM users WHERE age=33	$db->users->find(array("age" => 33), array("a" => 1, "b" => 1));
		SELECT a,b FROM users WHERE age=33 ORDER BY name	$db->users->find(array("age" => 33), array("a" => 1, "b" => 1))->sort(array("name" => 1));
		SELECT * FROM users WHERE age>33	$db->users->find(array("age" => array('$gt' => 33)));
		SELECT * FROM users WHERE age<33	$db->users->find(array("age" => array('$lt' => 33)));
		SELECT * FROM users WHERE name LIKE "%Joe%"	$db->users->find(array("name" => new MongoRegex("/Joe/")));
		SELECT * FROM users WHERE name LIKE "Joe%"	$db->users->find(array("name" => new MongoRegex("/^Joe/")));
		SELECT * FROM users WHERE age>33 AND age<=40	$db->users->find(array("age" => array('$gt' => 33, '$lte' => 40)));
		SELECT * FROM users ORDER BY name DESC	$db->users->find()->sort(array("name" => -1));
		SELECT * FROM users WHERE a=1 and b='q'	$db->users->find(array("a" => 1, "b" => "q"));
		SELECT * FROM users LIMIT 20, 10	$db->users->find()->limit(10)->skip(20);
		SELECT * FROM users WHERE a=1 or b=2	$db->users->find(array('$or' => array(array("a" => 1), array("b" => 2))));
		SELECT * FROM users LIMIT 1	$db->users->find()->limit(1);
		SELECT DISTINCT last_name FROM users	$db->command(array("distinct" => "users", "key" => "last_name"));
		SELECT COUNT(*y) FROM users	$db->users->count();
		SELECT COUNT(*y) FROM users where AGE > 30	$db->users->find(array("age" => array('$gt' => 30)))->count();
		SELECT COUNT(AGE) from users	$db->users->find(array("age" => array('$exists' => true)))->count();
		*/
	}

	private static function parseInsert(array $query) {
		/* INSERT INTO USERS VALUES(1,1)	$db->users->insert(array("a" => 1, "b" => 1)); */
		
		if(preg_match('/^INTO$/i', $query[1]) === 0) {
			throw new \UnexpectedValueException('INSERT keyword must be followed by INTO keyword');
		}

		$table = trim($query[2], '`');

		$index = 3;
		if(substr($query[$index], 0, 1) !== '(') {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		}

		//list of columns'names
		while(substr($query[$index], -1) !== ')') {
			$index++;
		}
		
		$imploded_columns = preg_replace('/\(\)/', '', join("", array_slice($query, 3, $index - 3 + 1)));
		$keys_list = array_map(function($el){
			return preg_replace('/,`/', '', $el);
		}, preg_split('/,/', $imploded_columns));
		
		if(preg_match('/values/i', $query[$index++]) === 0) {
			throw new \UnexpectedValueException('columns\' list must be followed by VALUES keyword');
		}

		if(substr($query[$index], 0, 1) !== '(') {
			throw new \UnexpectedValueException('parseInsert needs "(" after VALUES keyword');
		}

		//list of columns'names
		$valuesList = [];
		while($query[$index] !== ')') {
			if($query[$index] !== ',') {
				$values_list[] = $query[$index];
			}
			$index++;
		}

		if(count($keys_list) !== count($values_list)) {
			throw new \Exception('Columns number must match values number');
		}

		$params = array_combine ($keys_list, $values_list);

		return $this->insert($table, $params);
	}

	private static function parseDelete(array $query) {
		/* DELETE FROM users WHERE z="abc"	$db->users->remove(array("z" => "abc")); */
	}
	
	private static function parseUpdate(array $query) {
		/*
		UPDATE users SET a=1 WHERE b='q'	$db->users->update(array("b" => "q"), array('$set' => array("a" => 1)));
		UPDATE users SET a=a+2 WHERE b='q'	$db->users->update(array("b" => "q"), array('$inc' => array("a" => 2)));
		*/
	}

	//unhandled queries
	/*
	CREATE TABLE USERS (a Number, b Number)	Implicit or use MongoDB::createCollection().
	CREATE INDEX myindexname ON users(name)	$db->users->ensureIndex(array("name" => 1));
	CREATE INDEX myindexname ON users(name,ts DESC)	$db->users->ensureIndex(array("name" => 1, "ts" => -1));
	EXPLAIN SELECT * FROM users WHERE z=3	$db->users->find(array("z" => 3))->explain()
	*/
}
