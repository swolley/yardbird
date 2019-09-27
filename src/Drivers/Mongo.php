<?php
namespace Swolley\YardBird\Drivers;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Utils\QueryBuilder;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\TraitDatabase;
use	MongoDB\Client as MongoDB;
//use MongoDB\BSON as BSON;
use MongoDB\Driver\Command as MongoCmd;
//use MongoDB\Driver\Manager as MongoManager;
use MongoDB\BSON\Javascript as MongoJs;
use MongoDB\Driver\Exception as MongoException;
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectID;

class Mongo extends MongoDB implements IConnectable
{
	use TraitDatabase;
		
	/**
	 * @param	array	$params		connection parameters
	 * @param	bool	$debugMode	debug mode
	 */
	public function __construct(array $params, bool $debugMode = false)
	{
		$parsed_params = self::validateConnectionParams($params);
		$this->setInfo($params, $debugMode);

		try {
			$connection_string = "mongodb://{$parsed_params['user']}:{$parsed_params['password']}@{$parsed_params['host']}:{$parsed_params['port']}";
			parent::__construct(...[ $connection_string, ['authSource' => 'admin'] ]);
			$this->listDatabases();
		} catch (\Exception $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}

	public static function validateConnectionParams(array $params): array
	{
		//string $host, int $port, string $user, string $pass, string $dbName
		if (!isset($params['host'], $params['user'], $params['password'], $params['dbName'])) throw new BadMethodCallException("host, user, password, dbName are required");
		//defaults
		if(!isset($params['port'])) {
			$params['port'] = 27017;
		}

		return $params;
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function sql(string $query, $params = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$params = Utils::castToArray($params);
		$query = (new QueryBuilder)->sqlToMongo($query, $params);
		switch ($query->type) {
			case 'command':
				return $this->command($query->options, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
			case 'select':
				return $this->select($query->table, $query->filter, $query->options, $query->aggregate, $query->orderBy, $query->limit, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
			case 'insert':
				return $this->insert($query->table, $query->params, $query->ignore);
			case 'update':
				return $this->update($query->table, $query->params, $query->where);
			case 'delete':
				return $this->delete($query->table, $query->params);
			case 'procedure':
				return $this->procedure($query->table, $query->params);
		}
	}

	public function command(array $options = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		//FIXME also options needs to be binded
		try {
			$sth = $this->db->command($options);
			return self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function select(string $collection, array $filter = [], $options = [], array $aggregate = [], array $orderBy = [], $limit = null, int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			self::bindParams($filter);
			$sth = $this->{$this->_dbName}->{$collection}->find($filter, $options ?? []);
			if(!empty($aggregate)) {
				$sth->aggregate($aggregate);
			}	
			//ORDER BY
			if(!empty($orderBy)) {
				foreach($orderBy as $value) {
					if($value !== 1 && $value !== -1) throw new UnexpectedValueException("Unexpected order value. Use 1 for ASC, -1 for DESC");
				}
				$sth->sort($orderBy);
			}
			//LIMIT
			if($limit !== null) {
				if(is_integer($limit)){
					$sth->limit($limit);
				} elseif(is_array($limit) && count($limit) === 2) {
					$sth->limit($limit[1])->skip($limit[0]);
				} else {
					throw new UnexpectedValueException("Unexpected limit value. Can be integer or array of integers");
				}	
			} 
			
			return array_key_exists('count', $options) ? $sth->count() : self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
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
		$where = $where ?? [];
		if (!is_array($where)) throw new UnexpectedValueException('$where param must be of type array');

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
		$where = $where ?? [];
		if (!is_array($where)) throw new UnexpectedValueException('$where param must be of type array');

		try {
			self::bindParams($where);
			$response = $this->{$this->_dbName}->{$collection}->deleteMany($where);
			return $response->getDeletedCount() > 0;
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			self::bindParams($inParams);
			$jscode = new MongoJs('return db.eval("return ' . $name . '(' . implode(array_values($inParams)) . ');');
			$command = new MongoCmd(['eval' => $jscode]);
			$sth = $this->getManager()->executeCommand($this->_dbName, $command);
			return self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public static function fetch($sth, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		switch ($fetchMode) {
			case Connection::FETCH_ASSOC:
				$sth->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
				break;
			case Connection::FETCH_OBJ:
				$sth->setTypeMap(['root' => 'object', 'document' => 'object', 'array' => 'array']);
				break;	
			//case Connection::FETCH_CLASS:
				//	$response->setTypeMap([ 'root' => 'object', 'document' => $fetchModeParam, 'array' => 'array' ]);
				//	break;
			default:
				throw new MongoException\CommandException('Can\'t fetch. Only Object or Associative Array mode accepted');
		}

		return $sth->toArray();
	}

	public static function bindParams(array &$params, &$sth = null): bool
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
			
			$value = $value instanceof Regex ? new Regex(filter_var($value->getPattern(), $varType, $options)) : filter_var($value, $varType, $options);
			if ($value === null) return false;

			if($key === '_id') {
				$value = new ObjectID($value); 
			}
		}

		return true;
	}
}
