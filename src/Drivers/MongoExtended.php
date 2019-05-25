<?php
namespace Swolley\Database\Drivers;

use Swolley\Database\DBFactory;
use Swolley\Database\Interfaces\IConnectable;
use Swolley\Database\Utils\Utils;
use Swolley\Database\Utils\QueryBuilder;
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
use MongoLog;

class MongoExtended extends MongoDB implements IConnectable
{
	private $dbName;

	/**
	 * @param	array	$params	connection parameters
	 */
	public function __construct(array $params)
	{
		$params = self::validateConnectionParams($params);
		try{
			parent::__construct(...self::composeConnectionParams($params, [ 'authSource' => 'admin' ]));
			//$this->startSession();
			//$this->endSession();
			$this->dbName = $params['dbName'];
		} catch(\Exception $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}

	public static function validateConnectionParams(array $params): array
	{
		//string $host, int $port, string $user, string $pass, string $dbName
		if (!isset($params['host'], $params['user'], $params['password'], $params['dbName'])) {
			throw new BadMethodCallException("host, user, password, dbName are required");
		}
		return $params;
	}

	public static function composeConnectionParams(array $params, array $init_arr = []): array
	{
		$connection_string = "mongodb://{$params['user']}:{$params['password']}@{$params['host']}:{$params['port']}";

		return [
			$connection_string,
			$init_arr
		];
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function sql(string $query, $params = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$params = Utils::castToArray($params);
		$query = (new QueryBuilder)->createQuery($query, $params);
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

	public function select(string $collection, array $search = [], $options = null, int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			self::bindParams($search);
			//FIXME does options need to be filtered???
			/*foreach ($options as $key => &$param) {
				$param = filter_var($param);
			}*/

			$st = $this->{$this->dbName}->{$collection}->find($search, $options ?? []);
			if (array_key_exists('count', $options)) {
				return $st->count();
			} else {
				return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
			}
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function insert(string $collection, $params, bool $ignore = false)
	{
		$params = Utils::castToArray($params);
		try {
			self::bindParams($params);
			$response = $this->{$this->dbName}->{$collection}->insertOne($params, ['ordered' => !$ignore]);
			return $response->getInsertedId()['oid'];
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function update(string $collection, $params, $where = null): bool
	{
		if(is_null($where)) {
			$where = [];
		}

		if(gettype($where) !== 'array') {
			throw new UnexpectedValueException('$where param must be of type array');
		}

		$params = Utils::castToArray($params);
		try {
			self::bindParams($params);
			self::bindParams($where);

			$response = $this->{$this->dbName}->{$collection}->updateMany($where, ['$set' => $params], ['upsert' => FALSE]);
			return $response->getModifiedCount() > 0;
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function delete(string $collection, $where = null, array $params = null): bool
	{
		if(is_null($where)) {
			$where = [];
		}

		if(gettype($where) !== 'array') {
			throw new UnexpectedValueException('$where param must be of type array');
		}

		try {
			self::bindParams($where);
			$response = $this->{$this->dbName}->{$collection}->deleteMany($where);
			return $response->getDeletedCount() > 0;
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
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
				throw new MongoException\CommandException('Can\'t fetch. Only Object or Associative Array mode accepted');
		}
		return $st->toArray();
	}

	public static function bindParams(array &$params, &$st = null): bool
	{
		foreach ($params as &$value) {
			$varType = is_bool($value) ? FILTER_VALIDATE_BOOLEAN : is_int($value) ? FILTER_VALIDATE_INT : is_float($value) ? FILTER_VALIDATE_FLOAT : FILTER_DEFAULT;
			$options = [
				'options' => [
					'default' => null, // value to return if the filter fails
				]
			];
			if($varType === FILTER_VALIDATE_BOOLEAN) {
				$options['flags'] = FILTER_NULL_ON_FAILURE;
			}
			$value = filter_var($value, $varType, $options);
			if(is_null($value)) {
				return false;
			}
		}

		return true;
	}

}
