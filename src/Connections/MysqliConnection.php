<?php

namespace Swolley\YardBird\Connections;

use Swolley\YardBird\Interfaces\IRelationalConnectable;
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Utils\QueryBuilder;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Result;
use Swolley\YardBird\Interfaces\TraitDatabase;

class MysqliConnection extends \mysqli implements IRelationalConnectable
{
	use TraitDatabase;
	private $_inTransaction = false;

	/**
	 * @param	array	$params	connection parameters
	 * @param	bool	$debugMode	debug mode
	 */
	public function __construct(array $params, $debugMode = false)
	{
		$parsed_params = self::validateConnectionParams($params);
		$this->setInfo($params, $debugMode);

		try {
			parent::__construct($parsed_params['host'], $parsed_params['user'], $parsed_params['password'], $parsed_params['dbName'], $parsed_params['port']);
			if($this->connect_errno) {
				throw new ConnectionException($this->connect_err);
			}
			$this->set_charset($parsed_params['charset']);
		} catch (\Throwable $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}

	public static function validateConnectionParams(array $params): array
	{
		if (!isset($params['host'], $params['user'], $params['password'], $params['dbName']) || empty($params['host']) || empty($params['user']) || empty($params['password']) || empty($params['dbName']))
			throw new UnexpectedValueException("host, user, password can't be empty");

		//defaults
		$params['port'] = $params['port'] ?? 3306;
		$params['charset'] = $params['charset'] ?? 'UTF8';

		return $params;
	}

	public function sql(string $query, $params = []): Result
	{
		$builder = new QueryBuilder;
		$query = Utils::trimQueryString($query);
		$query = $builder->operatorsToStandardSyntax($query);
		$params = (array) $params;
		$builder->colonsToQuestionMarksPlaceholders($query, $params);

		$stmt = $this->prepare($query);
		if (!$stmt) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!self::bindParams($params, $stmt)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$stmt->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$query_type = strtolower(explode(' ', $query)[0]);
		if('select') {
			$stmt->store_result();
		}
		$result = new Result($stmt, $query_type);
		$stmt->close();
		return $result;
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null): Result
	{
		$builder = new QueryBuilder;
		$stmt = $this->prepare('SELECT ' . $builder->fieldsToSql($fields) . " FROM `$table` " . $builder->joinsToSql($join) . ' ' . $builder->whereToSql($where, true) . ' ' . $builder->orderByToSql($orderBy) . ' ' . $builder->limitToSql($limit));
		if (!$stmt) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!empty($where) && !self::bindParams($where, $stmt)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$stmt->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$stmt->store_result();
		return new Result($stmt, 'select');
	}

	public function insert(string $table, $params, bool $ignore = false): Result
	{
		$params = (array) $params;
		$keys_list = array_keys($params);
		$keys = '`' . implode('`, `', $keys_list) . '`';
		$values = rtrim(str_repeat("?, ", count($keys_list)), ', ');
		$stmt = $this->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO `$table` ($keys) VALUES ($values)");

		if (!$stmt) throw new QueryException("Cannot prepare query. Check the syntax.");
		if (!self::bindParams($params, $stmt)) throw new UnexpectedValueException('Cannot bind parameters');
		if (!$stmt->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$inserted_id = $stmt->insert_id;
		return new Result($stmt, 'insert', $inserted_id);
	}

	public function update(string $table, $params, $where = null): Result
	{
		$builder = new QueryBuilder;
		$params = (array) $params;
		$where = $builder->operatorsToStandardSyntax($where);
		//TODO how to bind where clause?
		$values = $builder->valuesListToSql($params);
		$builder->colonsToQuestionMarksPlaceholders($values, $params);
		if ($where !== null) {
			$builder->colonsToQuestionMarksPlaceholders($where, $params);
			$where = " WHERE {$where}";
		} else {
			$where = '';
		}

		$stmt = $this->prepare("UPDATE `{$table}` SET {$values} {$where}");
		if (!$stmt) {
			throw new QueryException("Cannot prepare query. Check the syntax. Error: [" . $this->errno . '] ' . $this->error);
		} elseif (!self::bindParams($params, $stmt)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$stmt->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		return new Result($stmt, 'update');
	}

	public function delete(string $table, $where = null, array $params = null): Result
	{
		if ($where !== null) {
			$builder = new QueryBuilder;
			$where = $builder->operatorsToStandardSyntax($where);
			$builder->colonsToQuestionMarksPlaceholders($where, $params);
		}

		$stmt = $this->prepare("DELETE FROM {$table} {$where}");
		if (!$stmt) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!self::bindParams($params, $stmt)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$stmt->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		return new Result($stmt, 'delete');
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [])
	{

		$procedure_in_params = rtrim(array_reduce($inParams, function ($sum) {
			return $sum .= "?, ";
		}, ''), ', ');
		$procedure_out_params = rtrim(array_reduce($inParams, function ($sum, $value) {
			return $sum .= ":$value, ";
		}, ''), ', ');
		$parameters_string = $procedure_in_params . (strlen($procedure_in_params) > 0 && strlen($procedure_out_params) > 0 ? ', ' : '') . $procedure_out_params;
		$stmt = $this->prepare("CALL $name($parameters_string);");

		if (!$stmt) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!self::bindParams($inParams, $stmt)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$stmt->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$outResult = [];
		self::bindOutParams($outParams, $stmt, $outResult);

		return count($outParams) > 0 ? $outResult : new Result($stmt, 'procedure');
	}

	public function showTables(): array
	{
		return array_map(function ($table) {
			return array_values($table)[0];
		}, $this->sql('SHOW TABLES')->fetch());
	}

	public function showColumns($tables)
	{
		if (is_string($tables)) {
			$tables = [$tables];
		} elseif (!is_array($tables)) {
			throw new UnexpectedValueException('Table name must be string or array of strings');
		}

		$query = "SHOW COLUMNS FROM ###name###";
		$columns = [];
		foreach ($tables as $table) {
			$cur = $this->sql(str_replace('###name###', $table, $query))->fetch();
			$columns[$table] = array_map(function ($column) {
				$column_name = $column['Field'];
				$column_data = [
					'type' => strtolower($column['Type']),
					'nullable' => $column['Null'] === 'YES',
					'default' => $column['Default']
				];

				return [$column_name => $column_data];
			}, $cur);
		}

		return $columns;
	}

	public static function bindParams(array &$params, &$stmt = null): bool
	{
		$params_values = array_values($params);
		return count($params_values) > 0 ? $stmt->bind_param(array_reduce($params, function ($total, $value) {
			return $total .= is_bool($value) || is_int($value) ? 'i' : (is_float($value) || is_double($value) ? 'd' : 's');
		}, ''), ...$params_values) : true;
	}

	public static function bindOutParams(&$params, &$stmt, &$outResult, int $maxLength = 40000): void
	{
		if (is_array($params) && is_array($outResult)) {
			foreach ($params as $value) {
				$outResult[$value] = null;
			}
			$stmt->bind_result(...$outResult);
		} elseif (is_string($params)) {
			$outResult = null;
			$stmt->bind_result($outResult);
		} else {
			throw new BadMethodCallException('$params and $outResult must have same type');
		}
	}

	public function transaction(): bool
	{
		$this->_inTransaction = true;
		return $this->autocommit(false);
	}

	public function commit($flags = null, $name = null): bool
	{
		if ($this->_inTransaction) {
			$this->_inTransaction = false;
			$committed = parent::commit();
			$this->autocommit(true);
			return $committed;
		}

		return false;
	}

	public function rollback($flags = null, $name = null): bool
	{
		if ($this->_inTransaction) {
			$this->_inTransaction = false;
			$rollbacked = parent::rollback();
			$this->autocommit(true);
			return $rollbacked;
		}

		return false;
	}
}
