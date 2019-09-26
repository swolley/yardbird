<?php
namespace Swolley\YardBird\Drivers;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Interfaces\IRelationalConnectable;
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Utils\QueryBuilder;

class MySqliExtended extends \mysqli implements IRelationalConnectable
{
	/**
	 * @var	boolean	$_debugMode	enables debug mode
	 */
	private $_debugMode;

	/**
	 * @param	array	$params	connection parameters
	 * @param	bool	$debugMode	debug mode
	 */
	public function __construct(array $params, $debugMode = false)
	{
		$params = self::validateConnectionParams($params);
		try {
			parent::__construct(...[ $params['host'], $params['user'], $params['password'], $params['dbName'], $params['port'] ]);
			$this->set_charset($params['charset']);
			$this->_debugMode = $debugMode;
		} catch(\Throwable $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode());
		}
	}

	public static function validateConnectionParams(array $params): array
	{
		if (!isset($params['host'], $params['user'], $params['password'])) {
			throw new BadMethodCallException("host, user, password are required");
		} elseif (empty($params['host']) || empty($params['user']) || empty($params['password'])) {
			throw new UnexpectedValueException("host, user, password can't be empty");
		}

		//default ports
		if (!isset($params['port'])) {
			$params['port'] = 3306;
		}

		//default charset
		if (!isset($params['charset'])) {
			$params['charset'] = 'UTF8';
		}

		/////////////////////////////////////////////////////////////
		if (!isset($params['dbName'])) {
			throw new BadMethodCallException("dbName is required");
		} elseif (empty($params['dbName'])) {
			throw new UnexpectedValueException("dbName can't be empty");
		}

		return $params;
	}

	public function sql(string $query, $params = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$query = Utils::trimQueryString($query);
		$query = QueryBuilder::operatorsToStandardSyntax($query);
		$params = Utils::castToArray($params);
		QueryBuilder::colonsToQuestionMarksPlaceholders($query, $params);			
		
		$sth = $this->prepare($query);

		if(!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$sth->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		
		$result = preg_match('/^update|^insert|^delete/i', $query) === 1 ? $sth->num_rows > 0 : self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		$sth->close();
		
		return $result;
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null, int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		//FIELDS
		$stringed_fields = '`' . join('`, `', $fields) . '`';
		//JOINS
		$stringed_joins = QueryBuilder::joinsToSql($join);
		//WHERE
		$stringed_where = QueryBuilder::whereToSql($where, true);
		//ORDER BY
		$stringed_order_by = QueryBuilder::orderByToSql($orderBy);
		//LIMIT
		$stringed_limit = QueryBuilder::limitToSql($limit);
		$sth = $this->prepare("SELECT {$stringed_fields} FROM {$table} {$stringed_joins} {$stringed_where} {$stringed_order_by} {$stringed_limit}");
		
		if(!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($where, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$sth->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		
		$result = self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		$sth->close();

		return $result;
	}

	public function insert(string $table, $params, bool $ignore = false)
	{
		$params = Utils::castToArray($params);
		$keys_list = array_keys($params);
		$keys = '`' . implode('`, `', $keys_list) . '`';
		$values = rtrim(str_repeat("?, ", count($keys_list)), ', ');
		$sth = $this->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO `{$table}` ({$keys}) VALUES ({$values})");
		
		if(!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$sth->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);

		$inserted_id = $sth->insert_id;
		$total_inserted = $sth->num_rows;
		
		return $inserted_id !== '0' ? $inserted_id : $total_inserted > 0;
	}

	public function update(string $table, $params, $where = null): bool
	{
		$params = Utils::castToArray($params);
		$where = QueryBuilder::operatorsToStandardSyntax($where);
		//TODO how to bind where clause?
		$values = QueryBuilder::valuesListToSql($params);
		if($where !== null) {
			QueryBuilder::colonsToQuestionMarksPlaceholders($where, $params);
			$where = " WHERE {$where}";
		} else {
			$where = '';
		}

		$sth = $this->prepare("UPDATE `{$table}` SET {$values} {$where}");
		
		if(!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$sth->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);

		return $sth->num_rows > 0;
	}

	public function delete(string $table, $where = null, array $params = null): bool
	{
		if($where !== null) {
			$where = QueryBuilder::operatorsToStandardSyntax($where);
			QueryBuilder::colonsToQuestionMarksPlaceholders($where, $params);
		}

		$sth = $this->prepare("DELETE FROM {$table} {$where}");
		
		if(!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$sth->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);

		return $sth->num_rows > 0;
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		//input params
		$procedure_in_params = '';
		foreach ($inParams as $value) {
			$procedure_in_params .= "?, ";
		}
		$procedure_in_params = rtrim($procedure_in_params, ', ');
		//output params
		$procedure_out_params = '';
		foreach ($outParams as $value) {
			$procedure_out_params .= ":$value, ";
		}
		$procedure_out_params = rtrim($procedure_out_params, ', ');

		$parameters_string = $procedure_in_params . (mb_strlen($procedure_in_params) > 0 && mb_strlen($procedure_out_params) > 0 ? ', ' : '') . $procedure_out_params;
		$sth = $this->prepare("CALL $name($parameters_string);");
		
		if(!$sth) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($inParams, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$sth->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		
		$outResult = [];
		self::bindOutParams($outParams, $sth, $outResult);
		
		return count($outParams) > 0 ? $outResult : self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
	}

	public static function fetch($sth, int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$meta = $sth->result_metadata();
		$response = [];
		if ($fetchMode === Connection::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = $meta->fetch_field_direct($fetchModeParam)) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & Connection::FETCH_CLASS && is_string($fetchModeParam)) {
			while ($row = !empty($fetchPropsLateParams) ? $meta->fetch_object($fetchModeParam, $fetchPropsLateParams) : $meta->fetch_object($fetchModeParam)) {
				array_push($response, $row);
			}
		} elseif($fetchMode & Connection::FETCH_OBJ) {
			while ($row = $meta->fetch_object()) {
				array_push($response, $row);
			}
		} else {
			$response = $meta->fetch_all(MYSQLI_ASSOC);
		}

		return $response;
	}

	public static function bindParams(array &$params, &$sth = null): bool
	{
		return $sth->bind_param(array_reduce($params, function($total, $value) {
			return $total .= is_bool($value) || is_int($value) ? 'i' : (is_float($value) || is_double($value) ? 'd' : 's');
		}, ''), ...$params);
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
}
