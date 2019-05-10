<?php
namespace Swolley\Database\Drivers;

use Swolley\Database\DBFactory;
use Swolley\Database\Interfaces\IRelationalConnectable;
use Swolley\Database\Utils\TraitUtils;
use Swolley\Database\Exceptions\ConnectionException;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

class MySqliExtended extends \mysqli implements IRelationalConnectable
{
	use TraitUtils;

	/**
	 * @param	array	$params	connection parameters
	 */
	public function __construct(array $params)
	{
		$params = self::validateConnectionParams($params);
		
		parent::__construct($params['host'], $params['user'], $params['password'], $params['dbName'], $params['port']);
		if ($this->connect_error()) {
			throw new ConnectionException($this->connect_error, $this->connect_errno);
		} else {
			$this->set_charset($params['charset']);
		}
	}

	public static function validateConnectionParams($params): array
	{
		if ($params['driver'] !== 'mysql') {
			throw new UnexpectedValueException("No {$params['driver']} driver available");
		}

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

	public function sql(string $query, $params = [], int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$query = self::trimCr($query);
		$params = self::castToArray($params);

		ksort($params);
		
		$total_params = count($params);
		$question_mark_placeholders = substr_count($query, '?');
		$colon_placeholders = [];
		preg_match_all('/(:\w+)/i', $query, $colon_placeholders);
		$colon_placeholders = array_pop(array_shift($colon_placeholders));

		if($question_mark_placeholders === 0 && $colon_placeholders === $total_params) {
			$reordered_params = [];
			foreach($colon_placeholders[0] as $param) {
				$key = array_search(ltrim($param, ':'), $params);
				if($key) {
					$reordered_params[] = $param[$key];
					str_replace($param, '?', $query);
				} else {
					throw new BadMethodCallException("`$param` not found in parameters list");
				}
			}

			$params = $reordered_params;
			unset($reordered_params);
		} elseif($colon_placeholders > 0 && $question_mark_placeholders > 0 || $question_mark_placeholders !== $total_params) {
			throw new BadMethodCallException('Possible incongruence in query placeholders');
		}			
		
		$st = $this->prepare($query);
		if(!self::bindParams($params, $st)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		}
		if(!$st->execute()) {
			throw new QueryException("{$this->errno}: {$this->error}", $this->errno);
		}

		$result =  self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		$st->close();

		return $result;
	}

	public function select(string $table, array $fields = [], array $where = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			$stringed_fields = '`' . join('`, `', $fields) . '`';

			ksort($where);
			$values = '';
			foreach ($where as $key => $value) {
				$values .= "`$key`=:$key AND ";
			}
			$stringed_where = rtrim($values, 'AND ');

			$st = $this->prepare("SELECT {$stringed_fields} FROM {$table} WHERE {$stringed_where}");
			if(!self::bindParams($where, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}");
			}
			return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function insert(string $table, $params, bool $ignore = false)
	{
		$params = self::castToArray($params);

		try {
			ksort($params);
			$keys_list = array_keys($params);
			$keys = '`' . implode('`, `', $keys_list) . '`';
			$values = ':' . implode(', :', $keys_list);

			$this->beginTransaction();

			$driver = $this->getAttribute(self::ATTR_DRIVER_NAME);
			$st = null;
			switch ($driver) {
				case 'mysql':
					$st = $this->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO `{$table}` ({$keys}) VALUES ({$values})");
					break;
				case 'oci':
					$st = $this->prepare("BEGIN INSERT INTO `{$table}` ({$keys}) VALUES ({$values}); " . ($ignore ? "EXCEPTION WHEN dup_val_on_index THEN null; " : '') . "END;");
					break;
				default:
					$st = null;
			}

			if (is_null($st)) {
				throw new \Exception('Requested driver still not supported');
			}

			if(!self::bindParams($params, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}");
			}
			$inserted_id = $this->lastInsertId();
			$total_inserted = $st->rowCount();
			$this->commit();

			return $inserted_id !== '0' ? $inserted_id : $total_inserted > 0;
		} catch (\PDOException $e) {
			$this->rollBack();
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function update(string $table, $params, string $where): bool
	{
		$params = self::castToArray($params);

		try {
			ksort($params);
			$values = '';
			foreach ($params as $key => $value) {
				$values .= "`$key`=:$key, ";
			}
			$values = rtrim($values, ', ');

			$st = $this->prepare("UPDATE `{$table}` SET {$values} WHERE {$where}");
			if(!self::bindParams($params, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}");
			}

			return $st->rowCount() > 0;
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function delete(string $table, string $where, array $params): bool
	{
		try {
			ksort($params);
			$st = $this->prepare("DELETE FROM {$table} WHERE {$where}");
			if(!self::bindParams($params, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}");
			}

			return $st->rowCount() > 0;
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			//input params
			$procedure_in_params = '';
			foreach ($inParams as $key => $value) {
				$procedure_in_params .= ":$key,";
			}
			$procedure_in_params = rtrim($procedure_in_params, ', ');

			//output params
			$procedure_out_params = '';
			foreach ($outParams as $value) {
				$procedure_out_params .= ":$value,";
			}
			$procedure_out_params = rtrim($procedure_out_params, ', ');

			$st = $this->prepare($this->constructProcedureString($name, $procedure_in_params, $procedure_out_params));
			if(!self::bindParams($inParams, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			$outResult = [];
			self::bindOutParams($outParams, $st, $outResult);
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}");
			}

			if (count($outParams) > 0) {
				return $outResult;
			} else {
				return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
			}
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode());
		}
	}

	public static function fetch($st, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$response = [];
		if ($fetchMode === BDFactory::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = $st->fetch($st)[$fetchModeParam]) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & BDFactory::FETCH_CLASS && is_string($fetchModeParam)) {
			while ($row = $st->fetch($st)) {
				array_push($response, new $fetchModeParam(...$row));
			}
		} else {
			while ($row = $st->fetch($st)) {
				array_push($response, $row);
			}
		}

		return $response;
	}

	public static function bindParams(array &$params, &$st = null): bool
	{
		// if(preg_match_all('/:[\S]*/', $st->queryString) > count($params)) {
		// 	throw new BadMethodCallException("Not enough values to bind placeholders");
		// }

		$varTypes = '';
		foreach ($params as $value) {
			$varTypes .= is_bool($value) || is_int($value) ? 'i' : is_float($value) || is_double($value) ? 'd' : 's';
        }
		
		if (!$st->bind_param($varTypes, array_values($params))) {
			return false;
		}

        return true;
	}

	public static function bindOutParams(&$params, &$st, &$outResult, int $maxLength = 40000): void
	{
		if (gettype($params) === 'array' && gettype($outResult) === 'array') {
			foreach ($params as $value) {
				$outResult[$value] = null;
				$st->bindParam(":$value", $outResult[$value], self::PARAM_STR | self::PARAM_INPUT_OUTPUT, $maxLength);
			}
		} elseif (gettype($params) === 'string') {
			$outResult = null;
			$st->bindParam(":$value", $outResult[$value], self::PARAM_STR | self::PARAM_INPUT_OUTPUT, $maxLength);
		} else {
			throw new BadMethodCallException('$params and $outResult must have same type');
		}
	}

	/**
	 * @param	array	$params	connection parameters
	 * @return	string	connection string for main drivers
	 */
	private static function getDefaultString(array $params): string
	{
		return "{$params['driver']}:host={$params['host']};port={$params['port']};dbname={$params['dbName']};charset={$params['charset']}";
	}

	/**
	 * @param	array	$params	connection parameters
	 * @return	string	connection string with tns for oci driver
	 * @throws	BadMethodCallException	if missing parameters
	 */
	private static function getOciString(array $params): string
	{
		$connect_data_name = $params['sid'] ? 'sid' : ($params['serviceName'] ? 'serviceName' : null);

		if (is_null($connect_data_name)) {
			throw new BadMethodCallException("Missing paramters");
		}

		$connect_data_value = $params[$connect_data_name];

		$tns = preg_replace(
			"/\n|\r|\n\r|\t/",
			'',
			"
			(DESCRIPTION = 
				(ADDRESS_LIST = 
					(ADDRESS = (PROTOCOL = TCP)(HOST = {$params['host']})(PORT = {$params['port']}))
				)
				(CONNECT_DATA = 
					(" . strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $connect_data_name)) . ' = ' . $connect_data_value	. ")
				)
			)"
		);

		return "oci:dbname={$tns};charset={$params['charset']}";
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

		if (is_null($procedure_string)) {
			throw new \Exception('Requested driver still not supported');
		}

		return str_replace(['###name###', '###params###'], [$name, $parameters_string], $procedure_string);
	}
}
