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
//use MongoDB\BSON as BSON;
use MongoDB\Driver\Command as MongoCmd;
//use MongoDB\Driver\Manager as MongoManager;
use MongoDB\BSON\Javascript as MongoJs;
use MongoDB\Driver\Exception as MongoException;
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectID;
//use MongoLog;

class MongoExtended extends MongoDB implements IConnectable
{
	/**
	 * @var	string	$_dbName	db name
	 * @var	boolean	$_debugMode	enables debug mode
	 */
	private $_dbName;
	private $_debugMode;

	/**
	 * @param	array	$params		connection parameters
	 * @param	bool	$debugMode	debug mode
	 */
	public function __construct(array $params, bool $debugMode = false)
	{
		$params = self::validateConnectionParams($params);
		try {
			parent::__construct(...self::composeConnectionParams($params, ['authSource' => 'admin']));
			$this->listDatabases();
			$this->_dbName = $params['dbName'];
			$this->_debugMode = $debugMode;
		} catch (\Exception $e) {
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
	public function sql(string $query, $params = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$params = Utils::castToArray($params);
		$query = (new QueryBuilder)->sqlToMongo($query, $params);
		switch ($query->type) {
			case 'command':
				return $this->command($query->options, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
				break;
			case 'select':
				return $this->select($query->table, $query->filter, $query->options, $query->aggregate, $query->orderBy, $query->limit, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
				break;
			case 'insert':
				return $this->insert($query->table, $query->params, $query->ignore);
				break;
			case 'update':
				return $this->update($query->table, $query->params, $query->where);
				break;
			case 'delete':
				return $this->delete($query->table, $query->params);
				break;
			case 'procedure':
				return $this->procedure($query->table, $query->params);
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

	public function select(string $collection, array $filter = [], $options = [], array $aggregate = [], array $orderBy = [], $limit = null, int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			self::bindParams($filter);
			$st = $this->{$this->_dbName}->{$collection}->find($filter, $options ?? []);
			if(!empty($aggregate)) {
				$st->aggregate($aggregate);
			}	
			//ORDER BY
			if(!empty($orderBy)) {
				foreach($orderBy as $value) {
					if($value !== 1 && $value !== -1) {
						throw new UnexpectedValueException("Unexpected order value. Use 1 for ASC, -1 for DESC");
					}
				}
				$st->sort($orderBy);
			}
			//LIMIT
			if(!is_null($limit)) {
				if(is_integer($limit)){
					$st->limit($limit);
				} elseif(is_array($limit) && count($limit) === 2) {
					$st->limit($limit[1])->skip($limit[0]);
				} else {
					throw new UnexpectedValueException("Unexpected limit value. Can be integer or array of integers");
				}	
			} 
			
			return array_key_exists('count', $options) ? $st->count() : self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function insert(string $collection, $params, bool $ignore = false)
	{
		$params = Utils::castToArray($params);
		try {
			self::bindParams($params);
			$response = $this->{$this->_dbName}->{$collection}->insertOne($params, ['ordered' => !$ignore]);

			return $response->getInsertedId()->__toString();
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function update(string $collection, $params, $where = null): bool
	{
		if (is_null($where)) {
			$where = [];
		}

		if (gettype($where) !== 'array') throw new UnexpectedValueException('$where param must be of type array');

		$params = Utils::castToArray($params);
		try {
			self::bindParams($params);
			self::bindParams($where);
			$response = $this->{$this->_dbName}->{$collection}->updateMany($where, ['$set' => $params], ['upsert' => FALSE]);
			
			return $response->getModifiedCount() > 0;
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function delete(string $collection, $where = null, array $params = null): bool
	{
		if (is_null($where)) {
			$where = [];
		}

		if (gettype($where) !== 'array') throw new UnexpectedValueException('$where param must be of type array');

		try {
			self::bindParams($where);
			$response = $this->{$this->_dbName}->{$collection}->deleteMany($where);
			
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
			$st = $this->getManager()->executeCommand($this->_dbName, $command);

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
		foreach ($params as $key => &$value) {
			$varType = is_bool($value) ? FILTER_VALIDATE_BOOLEAN : (is_int($value) ? FILTER_VALIDATE_INT : (is_float($value) ? FILTER_VALIDATE_FLOAT : FILTER_DEFAULT));
			$options = [
				'options' => [
					'default' => null, // value to return if the filter fails
				]
			];

			if ($varType === FILTER_VALIDATE_BOOLEAN) {
				$options['flags'] = FILTER_NULL_ON_FAILURE;
			}
			
			$value = $value instanceof Regex 
				? new Regex(filter_var($value->getPattern(), $varType, $options))
				: filter_var($value, $varType, $options);

			if (is_null($value)) return false;

			if($key === '_id') {
				$value = new ObjectID($value); 
			}
		}

		return true;
	}
}
