<?php

namespace Swolley\YardBird\Drivers;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\IRelationalConnectable;
use Swolley\YardBird\Interfaces\AbstractResult;
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Utils\QueryBuilder;
use Swolley\YardBird\Interfaces\TraitDatabase;
use Swolley\YardBird\Results\MysqliResult;

class Mysqli extends \mysqli implements IRelationalConnectable
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

	public function sql(string $query, $params = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): AbstractResult
	{
		$builder = new QueryBuilder;
		$query = Utils::trimQueryString($query);
		$query = $builder->operatorsToStandardSyntax($query);
		$params = (array) $params;
		$builder->colonsToQuestionMarksPlaceholders($query, $params);

		$sth = $this->prepare($query);
		if (!$sth) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!self::bindParams($params, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$sth->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$result = new MysqliResult($sth);
		$sth->close();
		return $result;
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null, int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): AbstractResult
	{
		$builder = new QueryBuilder;
		$sth = $this->prepare('SELECT ' . $builder->fieldsToSql($fields) . " FROM `$table` " . $builder->joinsToSql($join) . ' ' . $builder->whereToSql($where, true) . ' ' . $builder->orderByToSql($orderBy) . ' ' . $builder->limitToSql($limit));
		if (!$sth) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!empty($where) && !self::bindParams($where, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$sth->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		return new MysqliResult($sth, 'select');
	}

	public function insert(string $table, $params, bool $ignore = false): AbstractResult
	{
		$params = (array) $params;
		$keys_list = array_keys($params);
		$keys = '`' . implode('`, `', $keys_list) . '`';
		$values = rtrim(str_repeat("?, ", count($keys_list)), ', ');
		$sth = $this->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO `$table` ($keys) VALUES ($values)");

		if (!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if (!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if (!$sth->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$inserted_id = $sth->insert_id;
		return new MysqliResult($sth, 'insert', $inserted_id);
	}

	public function update(string $table, $params, $where = null): AbstractResult
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

		$sth = $this->prepare("UPDATE `{$table}` SET {$values} {$where}");
		if (!$sth) {
			throw new QueryException("Cannot prepare query. Check the syntax. Error: [" . $this->errno . '] ' . $this->error);
		} elseif (!self::bindParams($params, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$sth->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		return new MysqliResult($sth, 'update');
	}

	public function delete(string $table, $where = null, array $params = null): AbstractResult
	{
		if ($where !== null) {
			$builder = new QueryBuilder;
			$where = $builder->operatorsToStandardSyntax($where);
			$builder->colonsToQuestionMarksPlaceholders($where, $params);
		}

		$sth = $this->prepare("DELETE FROM {$table} {$where}");
		if (!$sth) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!self::bindParams($params, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$sth->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		return new MysqliResult($sth, 'delete');
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{

		$procedure_in_params = rtrim(array_reduce($inParams, function ($sum) {
			return $sum .= "?, ";
		}, ''), ', ');
		$procedure_out_params = rtrim(array_reduce($inParams, function ($sum, $value) {
			return $sum .= ":$value, ";
		}, ''), ', ');
		$parameters_string = $procedure_in_params . (strlen($procedure_in_params) > 0 && strlen($procedure_out_params) > 0 ? ', ' : '') . $procedure_out_params;
		$sth = $this->prepare("CALL $name($parameters_string);");

		if (!$sth) {
			throw new QueryException("Cannot prepare query. Check the syntax.");
		} elseif (!self::bindParams($inParams, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!$sth->execute()) {
			$this->rollback();
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$outResult = [];
		self::bindOutParams($outParams, $sth, $outResult);

		return count($outParams) > 0 ? $outResult : new MysqliResult($sth);
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

	public static function bindParams(array &$params, &$sth = null): bool
	{
		$params_values = array_values($params);
		return $sth->bind_param(array_reduce($params, function ($total, $value) {
			return $total .= is_bool($value) || is_int($value) ? 'i' : (is_float($value) || is_double($value) ? 'd' : 's');
		}, ''), ...$params_values);
	}

	public static function bindOutParams(&$params, &$sth, &$outResult, int $maxLength = 40000): void
	{
		if (is_array($params) && is_array($outResult)) {
			foreach ($params as $value) {
				$outResult[$value] = null;
			}
			$sth->bind_result(...$outResult);
		} elseif (is_string($params)) {
			$outResult = null;
			$sth->bind_result($outResult);
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
