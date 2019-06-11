<?php
namespace Swolley\Database\Drivers;

use Swolley\Database\DBFactory;
use Swolley\Database\Interfaces\IRelationalConnectable;
use Swolley\Database\Utils\Utils;
use Swolley\Database\Exceptions\ConnectionException;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;
use Swolley\Database\Utils\QueryBuilder;

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
			parent::__construct(...self::composeConnectionParams($params));
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

	public static function composeConnectionParams(array $params, array $init_arr = []): array
	{
		return [
			$params['host'], 
			$params['user'], 
			$params['password'], 
			$params['dbName'], 
			$params['port']
		];
	}

	public function sql(string $query, $params = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$query = Utils::trimQueryString($query);
		$query = QueryBuilder::operatorsToStandardSyntax($query);
		$params = Utils::castToArray($params);
		QueryBuilder::colonsToQuestionMarksPlaceholders($query, $params);			
		
		$st = $this->prepare($query);

		if(!$st) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$st->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		
		$result = preg_match('/^update|^insert|^delete/i', $query) === 1 ? $st->num_rows > 0 : self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		$st->close();
		
		return $result;
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null, int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
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
		$st = $this->prepare("SELECT {$stringed_fields} FROM {$table} {$stringed_joins} {$stringed_where} {$stringed_order_by} {$stringed_limit}");
		
		if(!$st) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($where, $st)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$st->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		
		$result = self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		$st->close();

		return $result;
	}

	public function insert(string $table, $params, bool $ignore = false)
	{
		$params = Utils::castToArray($params);
		$keys_list = array_keys($params);
		$keys = '`' . implode('`, `', $keys_list) . '`';
		$values = rtrim(str_repeat("?, ", count($keys_list)), ', ');
		$st = $this->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO `{$table}` ({$keys}) VALUES ({$values})");
		
		if(!$st) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$st->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);

		$inserted_id = $st->insert_id;
		$total_inserted = $st->num_rows;
		
		return $inserted_id !== '0' ? $inserted_id : $total_inserted > 0;
	}

	public function update(string $table, $params, $where = null): bool
	{
		$params = Utils::castToArray($params);
		$where = QueryBuilder::operatorsToStandardSyntax($where);
		//TODO how to bind where clause?
		$values = QueryBuilder::valuesListToSql($params);
		if(!is_null($where)) {
			QueryBuilder::colonsToQuestionMarksPlaceholders($where, $params);
			$where = " WHERE {$where}";
		} else {
			$where = '';
		}

		$st = $this->prepare("UPDATE `{$table}` SET {$values} {$where}");
		
		if(!$st) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$st->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);

		return $st->num_rows > 0;
	}

	public function delete(string $table, $where = null, array $params = null): bool
	{
		if(!is_null($where)) {
			$where = QueryBuilder::operatorsToStandardSyntax($where);
			QueryBuilder::colonsToQuestionMarksPlaceholders($where, $params);
		}

		$st = $this->prepare("DELETE FROM {$table} {$where}");
		
		if(!$st) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$st->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);

		return $st->num_rows > 0;
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		//input params
		$procedure_in_params = '';
		foreach ($inParams as $key => $value) {
			$procedure_in_params .= "?, ";
		}
		$procedure_in_params = rtrim($procedure_in_params, ', ');
		//output params
		$procedure_out_params = '';
		foreach ($outParams as $value) {
			$procedure_out_params .= ":$value, ";
		}
		$procedure_out_params = rtrim($procedure_out_params, ', ');

		$st = $this->prepare($this->constructProcedureString($name, $procedure_in_params, $procedure_out_params));
		
		if(!$st) throw new QueryException("Cannot prepare query. Check the syntax.");
		if(!self::bindParams($inParams, $st)) throw new UnexpectedValueException('Cannot bind parameters');
		if(!$st->execute()) throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		
		$outResult = [];
		self::bindOutParams($outParams, $st, $outResult);
		
		return count($outParams) > 0 
			? $outResult 
			: self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
	}

	public static function fetch($st, int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$meta = $st->result_metadata();
		$response = [];
		if ($fetchMode === DBFactory::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = $meta->fetch_field_direct($fetchModeParam)) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & DBFactory::FETCH_CLASS && is_string($fetchModeParam)) {
			while ($row = !empty($fetchPropsLateParams) ? $meta->fetch_object($fetchModeParam, $fetchPropsLateParams) : $meta->fetch_object($fetchModeParam)) {
				array_push($response, $row);
			}
		} elseif($fetchMode & DBFactory::FETCH_OBJ) {
			while ($row = $meta->fetch_object()) {
				array_push($response, $row);
			}
		} else {
			$response = $meta->fetch_all(MYSQLI_ASSOC);
		}

		return $response;
	}

	public static function bindParams(array &$params, &$st = null): bool
	{
		return $st->bind_param(array_reduce($params, function($total, $value) {
			return $total .= is_bool($value) || is_int($value) ? 'i' : (is_float($value) || is_double($value) ? 'd' : 's');
		}, ''), ...$params);
	}

	public static function bindOutParams(&$params, &$st, &$outResult, int $maxLength = 40000): void
	{
		if (gettype($params) === 'array' && gettype($outResult) === 'array') {
			foreach ($params as $value) {
				$outResult[$value] = null;
			}
			$st->bind_result(...$outResult);
		} elseif (gettype($params) === 'string') {
			$outResult = null;
			$st->bind_result($outResult);
		} else {
			throw new BadMethodCallException('$params and $outResult must have same type');
		}
		
	}

	/**
	 * @param	string	$name	procedure name
	 * @param	string	$in		stringed input parameters
	 * @param	string	$out	stringed output parameters
	 * @return	string	composed procedure query string
	 */
	protected function constructProcedureString(string $name, string $in = '', string $out = ''): string
	{
		$parameters_string = $in . (strlen($in) > 0 && strlen($out) > 0 ? ', ' : '') . $out;
		$procedure_string = null;
		switch ($this->getAttribute(self::ATTR_DRIVER_NAME)) {
			case 'pgsql':
			case 'mysql':
				$procedure_string = "CALL ###name###(###params###);";
				break;
			case 'mssql':
				$procedure_string = "EXEC ###name### ###params###;";
				break;
			case 'oci':
				$procedure_string = "BEGIN ###name### (###params###); END;";
				break;
			default:
				$procedure_string = null;
		}

		if (is_null($procedure_string)) throw new \Exception('Requested driver still not supported');

		return str_replace(['###name###', '###params###'], [$name, $parameters_string], $procedure_string);
	}
}
