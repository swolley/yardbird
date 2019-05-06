<?php
namespace Swolley\Database\Drivers;

use Swolley\Database\DBFactory;
use Swolley\Database\Interfaces\IConnectable;
use Swolley\Database\Utils\TraitUtils;
use Swolley\Database\Utils\TraitQueryBuilder;
use Swolley\Database\Exceptions\ConnectionException;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;
use	MongoDB\Client as MongoDB;
use MongoDB\BSON as BSON;
use MongoDB\Driver\Command as MongoCmd;
use MongoDB\Driver\Manager as MongoManager;
use MongoDB\BSON\Javascript as MongoJs;
use MongoDB\Driver\Exception as MongoException;
use MongoDB\Driver\MongoConnectionException;
use MongoLog;

class MongoExtended extends MongoDB
{
	use TraitUtils;
	use TraitQueryBuilder;

	private $dbName;

	/**
	 * @param	array	$params	connection parameters
	 */
	public function __construct(array $params)
	{
		$params = self::validateConnectionParams($params);
		try{
			parent::__construct(self::constructConnectionString($params), [
				'authSource' => 'admin',
			]);
			$this->dbName = $params['dbName'];
		} catch(MongoConnectionException $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}

	public static function validateConnectionParams($params): array
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
		return "mongodb://{$params['user']}:{$params['password']}@{$params['host']}:{$params['port']}";
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function sql(string $query, $params = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$params = self::castToArray($params);
		$query = $this->createQuery($query, $params);
		switch ($query['type']) {
			case 'command':
				return $this->command($query['options'], $fetchMode, $fetchModeParam, $fetchPropsLateParams);
				break;
			case 'select':
				return $this->select($query['table'], $query['params'], $query['options'], $fetchMode, $fetchModeParam, $fetchPropsLateParams);
				break;
			case 'insert':
				return $this->insert($query['table'], $query['params'], $query['ignore']);
				break;
			case 'update':
				return $this->update($query['table'], $query['params'], $query['where']);
				break;
			case 'delete':
				return $this->delete($query['table'], $query['params']);
				break;
			case 'procedure':
				return $this->procedure($query['name'], $query['params']);
				break;
		}
	}

	public function command(array $options = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		//FIXME also options needs to be binded
		try {
			$st = $this->db->command($options);
			return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	//function select(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []);
	public function select(string $collection, array $search = [], array $options = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			self::bindParams($search);
			//FIXME does options need to be filtered???
			/*foreach ($options as $key => &$param) {
				$param = filter_var($param);
			}*/

			$st = $this->{$this->dbName}->{$collection}->find($search, $options);
			if (array_key_exists('count', $options)) {
				return $st->count();
			} else {
				return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
			}
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * execute insert query
	 * @param   string  $collection     collection name
	 * @param   array|object   $params	assoc array with placeholder's name and relative values
	 * @param   boolean $ignore         performes an 'insert ignore' query
	 * @return  mixed                   new row id or error message
	 */
	public function insert(string $collection, $params, bool $ignore = false)
	{
		$params = self::castToArray($params);
		try {
			self::bindParams($params);
			$response = $this->{$this->dbName}->{$collection}->insertOne($params, ['ordered' => !$ignore]);
			return $response->getInsertedId()['oid'];
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
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
		$params = self::castToArray($params);
		try {
			self::bindParams($params);
			self::bindParams($where);

			$response = $this->{$this->dbName}->{$collection}->updateMany($where, ['$set' => $params], ['upsert' => FALSE]);
			return $response->getModifiedCount();
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
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
			self::bindParams($where);
			$response = $this->{$this->dbName}->{$collection}->deleteMany($where);
			return $response->getDeletedCount() ? TRUE : FALSE;
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * execute stored procedure
	 * @param   string  $name           stored procedure name
	 * @param   array   $params         (optional) assoc array with paramter's names and relative values
	 * @return  mixed                   stored procedure result or error message
	 */
	public function procedure(string $name, array $inParams = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			self::bindParams($inParams);
			$jscode = new MongoJs('return db.eval("return ' . $name . '(' . implode(array_values($inParams)) . ');');
			$command = new MongoCmd(['eval' => $jscode]);
			$st = $this->getManager()->executeCommand($this->dbName, $command);
			return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public static function bindParams(array &$params, &$st = null): void
	{
		foreach ($params as &$param) {
			$param = filter_var($param);
		}
	}

	public static function fetch($st, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		switch ($fetchMode) {
			case DBFactory::FETCH_ASSOC:
				$st->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
				break;
			case DBFactory::FETCH_OBJ:
				$st->setTypeMap(['root' => 'object', 'document' => 'object', 'array' => 'array']);
				break;
				//case DBFactory::FETCH_CLASS:
				//	$response->setTypeMap([ 'root' => 'object', 'document' => $fetchModeParam, 'array' => 'array' ]);
				//	break;
			default:
				throw new MongoException\CommandException('Can fetch only Object or Associative Array');
		}
		return $st->toArray();
	}
}
