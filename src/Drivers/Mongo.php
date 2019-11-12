<?php

namespace Swolley\YardBird\Drivers;

use Swolley\YardBird\Interfaces\IConnectable;
use Swolley\YardBird\Interfaces\AbstractResult;
use Swolley\YardBird\Utils\QueryBuilder;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Exceptions\NotImplementedException;
use Swolley\YardBird\Interfaces\TraitDatabase;
use	MongoDB\Client as MongoDB;
use MongoDB\Driver\Command as MongoCmd;
use MongoDB\BSON\Javascript as MongoJs;
use MongoDB\Driver\Exception as MongoException;
use MongoDB\Model\CollectionInfo;
use MongoDB\BSON\Regex;
use MongoDB\BSON\ObjectID;
use Swolley\YardBird\Results\MongoResult;

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
			parent::__construct($connection_string, ['authSource' => 'admin']);
			$this->listDatabases();
		} catch (\Exception $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}

	public static function validateConnectionParams(array $params): array
	{
		//string $host, int $port, string $user, string $pass, string $dbName
		if (!isset($params['host'], $params['user'], $params['password'], $params['dbName']) || empty($params['host']) || empty($params['user']) || empty($params['password']) || empty($params['dbName']))
			throw new BadMethodCallException("host, user, password, dbName are required");
		//defaults
		$params['port'] = $params['port'] ?? 27017;
		return $params;
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public function sql(string $query, $params = []): AbstractResult
	{
		$params = (array) $params;
		$query = (new QueryBuilder)->sqlToMongo($query, $params);
		switch ($query->type) {
			case 'command':
				return $this->command($query->options);
			case 'select':
				return $this->select($query->table, $query->filter, $query->options, $query->aggregate, $query->orderBy, $query->limit);
			case 'insert':
				return $this->insert($query->table, $query->params, $query->ignore);
			case 'update':
				return $this->update($query->table, $query->params, $query->where);
			case 'delete':
				return $this->delete($query->table, $query->params);
			case 'procedure':
				return $this->procedure($query->table, $query->params);
		}

		throw new UnexpectedValueException('Unrecognized query');
	}

	public function command(array $options = []): AbstractResult
	{
		//FIXME also options needs to be binded
		try {
			$sth = $this->db->command($options);
			return new MongoResult($sth, 'command');
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function select(string $collection, array $filter = [], $options = [], array $aggregate = [], array $orderBy = [], $limit = null): AbstractResult
	{
		try {
			self::bindParams($filter);
			$sth = $this->{$this->_dbName}->{$collection}->find($filter, $options ?? []);
			if (!empty($aggregate)) {
				$sth->aggregate($aggregate);
			}
			//ORDER BY
			if (!empty($orderBy)) {
				foreach ($orderBy as $value) {
					if ($value !== 1 && $value !== -1) throw new UnexpectedValueException("Unexpected order value. Use 1 for ASC, -1 for DESC");
				}
				$sth->sort($orderBy);
			}
			//LIMIT
			if ($limit !== null) {
				if (is_integer($limit)) {
					$sth->limit($limit);
				} elseif (is_array($limit) && count($limit) === 2) {
					$sth->limit($limit[1])->skip($limit[0]);
				} else {
					throw new UnexpectedValueException("Unexpected limit value. Can be integer or array of integers");
				}
			}

			return new MongoResult($sth, 'select');
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function insert(string $collection, $params, bool $ignore = false): AbstractResult
	{
		$params = (array) $params;
		try {
			self::bindParams($params);
			$response = $this->{$this->_dbName}->{$collection}->insertOne($params, ['ordered' => !$ignore]);
			return new MongoResult($response, 'insert', $response->getInsertedId()->__toString());
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function update(string $collection, $params, $where = null): AbstractResult
	{
		$where = $where ?? [];
		if (!is_array($where)) throw new UnexpectedValueException('$where param must be of type array');

		$params = (array) $params;
		try {
			self::bindParams($params);
			self::bindParams($where);
			$response = $this->{$this->_dbName}->{$collection}->updateMany($where, ['$set' => $params], ['upsert' => FALSE]);
			return new MongoResult($response, 'update');
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function delete(string $collection, $where = null, array $params = null): AbstractResult
	{
		$where = $where ?? [];
		if (!is_array($where)) throw new UnexpectedValueException('$where param must be of type array');

		try {
			self::bindParams($where);
			$response = $this->{$this->_dbName}->{$collection}->deleteMany($where);
			return new MongoResult($response, 'delete');
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [])
	{
		try {
			self::bindParams($inParams);
			$jscode = new MongoJs('return db.eval("return ' . $name . '(' . implode(array_values($inParams)) . ');');
			$command = new MongoCmd(['eval' => $jscode]);
			$sth = $this->getManager()->executeCommand($this->_dbName, $command);
			return new MongoResult($sth, 'procedure');
		} catch (MongoException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	function showTables(): array
	{
		return array_map(function (CollectionInfo $collection) {
			return $collection->name;
		}, $this->db->listCollections());
	}

	function showColumns($tables)
	{
		throw new NotImplementedException('MongoDB is schemaless and is not possible to get a unique data structure');
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
			if ($value === null) {
				return false;
			} elseif ($key === '_id') {
				$value = new ObjectID($value);
			}
		}

		return true;
	}
}
