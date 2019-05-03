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
		return $this->queryParser($query, $params);
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

	private function queryParser(string $query, array $params = []) {
		//$exploded = explode(' ', $query);
		if(preg_match('/^select/i', $query) === 1) {
			self::parseSelect($query, $params);
		}elseif(preg_match('/^insert/i', $query) === 1) {
			self::parseInsert($query, $params);
		} elseif(preg_match('/^delete from/i', $query) === 1) {
			self::parseDelete($query, $params);
		} elseif(preg_match('/^update/i', $query) === 1) {
			self::parseUpdate($query, $params);
		} else {
			throw new \UnexpectedValueException('queryParser is unable to convert query');
		}
	}

	private function parseSelect(string $query, array $params = []) {
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

	private function parseInsert(string $query, array $params = []) {
		//recognize ignore keyword
		$ignore = false;
		if(preg_match('/^(insert\s)(ignore\s)/i', $query) === 1) {
			$ignore = true;
		}
		$query = rtrim(preg_replace('/^(insert\s)(ignore\s)?(into\s)/i', '', $query), ';');
		
		//splits main macro blocks (table, columns, values)
		$matches = [];
		preg_match_all('/^(`?\w+(?=\s*)`?\s?)(\([^(]*\)\s?)(values\s?)(\([^(]*\))$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);
		
		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);
		if(preg_match('/^values/i', $query[0][0]) === 1) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		}

		//list of columns'names
		$keys_list = preg_split('/,\s?/', preg_replace('/\(|\)/', '', array_shift($query)[0]));
		if(count($keys_list) === 0) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		}
		$keys_list = array_map(function($key){
			return trim($key, '`');
		}, $keys_list);
		
		//list of columns'values
		if(preg_match('/^values/i', array_shift($query)[0]) === 0) {
			throw new \UnexpectedValueException('columns\' list must be followed by VALUES keyword');
		}

		$values_list = preg_split('/,\s?/', preg_replace('/\(|\)/', '', array_shift($query)[0]));
		if(count($values_list) === 0) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' values');
		}
		$values_list = array_map(function($value){
			return $this->castValue($value);
		}, $values_list);

		if(count($keys_list) !== count($values_list)) {
			throw new \Exception('Columns count must match values count');
		}

		//substitute params in array of values
		foreach($params as $key => $value) {
			if($index = array_search(':' . $key, $values_list)) {
				$values_list[$index] = $value;
			}
		}

		//compose array column/value
		$params = array_combine ($keys_list, $values_list);

		return $this->insert($table, $params, $ignore);
	}

	private function parseDelete(string $query, array $params = []) {
		/* DELETE FROM users WHERE z="abc"	$db->users->remove(array("z" => "abc")); */

		//splits main macro blocks (table, columns, values)
		$query = rtrim(preg_replace('/^(delete from\s)/i', '', $query), ';');
		$matches = [];
		preg_match_all('/^(`?\w+(?=\s*)`?\s?)(where\s?)(.*)$/i', $query, $matches);
		$query = array_slice($matches, 1);
		unset($matches);

		//set table name
		$table = preg_replace('/`|\s/', '', array_shift($query)[0]);
		if(preg_match('/^where/i', $query[0][0]) === 0) {
			throw new \UnexpectedValueException('parseInsert needs to know columns\' names');
		} else {
			array_shift($query);
		}

		//where params
		$query = preg_split('/\s/', $query[0][0]);
		for($i = 0; $i < count($query); $i++) {
			if(strpos($query[$i], '(') !== false || strpos($query[$i], ')') !== false) {
				if(substr($query[$i], 0, 1) === '('){
					$to_add = substr($query[$i], 1);
					$second_part = array_slice($query, $i + 1);
					$first_part = array_slice($query, 0, $i);
					$first_part[] = '(';
					$first_part[] = $to_add;
					$query = array_merge($first_part, $second_part);
				}elseif(substr($query[$i], -1, 1) === ')') {
					$to_add = substr($query[$i], 1);
					$second_part = array_slice($query, $i + 1);
					$first_part = array_slice($query, 0, $i);
					$first_part[] = ')';
					$first_part[] = $to_add;
					$query = array_merge($first_part, $second_part);
				}
			}
		}

		$where_params = $this->parseOperators($query, $params, 0, 0);
		echo var_dump($where_params);
	}
	
	private function parseUpdate(string $query, array $params = []) {
		/*
		UPDATE users SET a=1 WHERE b='q'	$db->users->update(array("b" => "q"), array('$set' => array("a" => 1)));
		UPDATE users SET a=a+2 WHERE b='q'	$db->users->update(array("b" => "q"), array('$inc' => array("a" => 2)));
		*/
	}

	private function parseOperators(array $query, array $params, &$i = 0, &$nested_level = 0) {
		$where_params = [];

		for($i; $i < count($query); $i++) {
			if(preg_match('/!?=|<=?|>=?/i', $query[$i]) === 1) {
				$splitted = preg_split('/(=|!=|<>|>=|<=|>(?!=)|<(?<!=)(?!>))/i', $query[$i], null, PREG_SPLIT_DELIM_CAPTURE);
				switch($splitted[1]) {
					case '=': 
						$operator = '$eq';
						break;
					case '!=': 
					case '<>': 
						$operator = '$ne';
						break;
					case '<': 
						$operator = '$lt';
						break;
					case '<=': 
						$operator = '$lte';
						break;
					case '>': 
						$operator = '$gt';
						break;
					case '>=': 
						$operator = '$gte';
						break;
					default:
						throw new \UnexpectedValueException('Unrecognised operator');
				}

				$splitted[2] = $this->castValue($splitted[2]);
				$trimmed = ltrim($splitted[2], ':');
				if(isset($params[$trimmed])) {
					$splitted[2] = $params[$trimmed];
				}

				$where_params[] = [ $splitted[0] => [ $operator => $splitted[2] ] ];
			} elseif(preg_match('/and|&&|or|\|\|/i', $query[$i]) === 1) {
				$where_params[] = $query[$i];
			} elseif($query[$i] === '(') {
				$where_params[] = $this->parseOperators($query, $params, ++$i, ++$nested_level);
			} elseif($query[$i] === ')') {
				$i++;
				$nested_level--;
				return $where_params;
			} else {
				throw new \UnexpectedValueException('Unexpected keyword ' . $query[$i]);
			}
		}

		return $where_params;
	}

	private function castValue($value) {
		if(preg_match("/^'|\"\w+'|\"$/", $value)) {
			return preg_replace("/'|\"/", '', $value);
		} elseif(is_numeric($value)) {
			return $value + 0;
		} elseif(is_bool($value)) {
			return (bool)$value;
		} else {
			return $value;
		}
	}

	//unhandled queries
	/*
	CREATE TABLE USERS (a Number, b Number)	Implicit or use MongoDB::createCollection().
	CREATE INDEX myindexname ON users(name)	$db->users->ensureIndex(array("name" => 1));
	CREATE INDEX myindexname ON users(name,ts DESC)	$db->users->ensureIndex(array("name" => 1, "ts" => -1));
	EXPLAIN SELECT * FROM users WHERE z=3	$db->users->find(array("z" => 3))->explain()
	*/
}
