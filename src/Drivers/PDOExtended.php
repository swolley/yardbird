<?php
namespace Swolley\Database\Drivers;

use Swolley\Database\DBFactory;
use Swolley\Database\Interfaces\IRelationalConnectable;
use Swolley\Database\Utils\Utils;
use Swolley\Database\Utils\QueryBuilder;
use Swolley\Database\Exceptions\ConnectionException;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

class PDOExtended extends \PDO implements IRelationalConnectable
{
	/**
	 * @var	boolean	$_debugMode	enables debug mode
	 */
	private $_debugMode;

	/**
	 * @param	array	$params	connection parameters
	 * @param	bool	$debugMode	debug mode
	 */
	public function __construct(array $params, bool $debugMode = false)
	{
		$params = self::validateConnectionParams($params);
		
		try{
			parent::__construct(...self::composeConnectionParams($params));
			if (error_reporting() === E_ALL) {
				parent::setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
			}
			$this->_debugMode = $debugMode;
		} catch(\PDOException $e) {
			throw new ConnectionException($e->getMessage(), $e->getCode(), $e);
		}

	}

	public static function validateConnectionParams(array $params): array
	{
		if (!in_array($params['driver'], self::getAvailableDrivers())) {
			throw new UnexpectedValueException("No {$params['driver']} driver available");
		}

		if (!isset($params['host'], $params['user'], $params['password'])) {
			throw new BadMethodCallException("host, user, password are required");
		} elseif (empty($params['host']) || empty($params['user']) || empty($params['password'])) {
			throw new UnexpectedValueException("host, user, password can't be empty");
		}

		//default ports
		if (!isset($params['port'])) {
			switch ($params['driver']) {
				case 'mysql':
					$params['port'] = 3306;
					break;
				case 'pgsql':
					$params['port'] = 5432;
				case 'mssql';
					$params['port'] = 1433;
				case 'oci':
					$params['port'] = 1521;
			}
		}

		//default charset
		if (!isset($params['charset'])) {
			$params['charset'] = 'UTF8';
		}

		/////////////////////////////////////////////////////////////
		if ($params['driver'] !== 'oci' && !isset($params['dbName'])) {
			throw new BadMethodCallException("dbName is required");
		} elseif ($params['driver'] !== 'oci' && empty($params['dbName'])) {
			throw new UnexpectedValueException("dbName can't be empty");
		}

		if ($params['driver'] === 'oci' && (!isset($params['sid']) || empty($params['sid']))	&& (!isset($params['serviceName']) || empty($params['serviceName']))) {
			throw new BadMethodCallException("sid or serviceName must be specified");
		}

		return $params;
	}

	public static function composeConnectionParams(array $params, array $init_arr = []): array
	{
		return [
			$params['driver'] === 'oci'	? self::getOciString($params) : self::getDefaultString($params),
			$params['user'], 
			$params['password']
		];
	}

	public function sql(string $query, $params = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$params = Utils::castToArray($params);
		$query = Utils::trimQueryString($query);
		if($this->getAttribute(self::ATTR_DRIVER_NAME) !== 'oci'){
			//because in postgres && has a different meaning than OR
			$query = QueryBuilder::operatorsToStandardSyntax($query);
		}

		//TODO add the function developed for mysqli
		
		try {
			//ksort($params);
			$st = $this->prepare($query);
			if(!self::bindParams($params, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}
			
            return preg_match('/^select/i', $query) ? $st->rowCount() > 0 : self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null, int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			//FIELDS
			$stringed_fields = '`' . join('`, `', $fields) . '`';
			//JOINS
			$stringed_joins = QueryBuilder::joinsToSql($join);
			//WHERE
			$stringed_where = QueryBuilder::whereToSql($where);
			//ORDER BY
			$stringed_order_by = QueryBuilder::orderByToSql($orderBy);
			//LIMIT
			$stringed_limit = QueryBuilder::limitToSql($limit);

			$st = $this->prepare("SELECT {$stringed_fields} FROM {$table} {$stringed_joins} {$stringed_where} {$stringed_order_by} {$stringed_limit}");
			
			if(!self::bindParams($where, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}
			
			return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function insert(string $table, $params, bool $ignore = false)
	{
		$params = Utils::castToArray($params);
		try {
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

			if (is_null($st)) throw new \Exception('Requested driver still not supported');
			if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}
			
			$inserted_id = $this->lastInsertId();
			$total_inserted = $st->rowCount();
			$this->commit();

			return $inserted_id !== '0' ? $inserted_id : $total_inserted > 0;
		} catch (\PDOException $e) {
			$this->rollBack();
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function update(string $table, $params, $where = null): bool
	{
		$params = Utils::castToArray($params);
		if(!is_null($where) && gettype($where) !== 'string') {
			throw new UnexpectedValueException('$where param must be of type string');
		} elseif(!is_null($where)) {
			if($this->getAttribute(self::ATTR_DRIVER_NAME) !== 'oci'){
				//because in postgres && has a different meaning than OR
				$where = QueryBuilder::operatorsToStandardSyntax($where);
			}
		}
		//TODO how to bind where clause?
		try {
			$values = QueryBuilder::valuesListToSql($params);

			$st = $this->prepare("UPDATE `{$table}` SET {$values}" . (!is_null($where) ? " WHERE {$where}" : ''));
			
			if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return $st->rowCount() > 0;
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function delete(string $table, $where = null, array $params = null): bool
	{
		if(!is_null($where) && gettype($where) !== 'string') {
			throw new UnexpectedValueException('$where param must be of type string');
		} elseif(!is_null($where)) {
			if($this->getAttribute(self::ATTR_DRIVER_NAME) !== 'oci'){
				//because in postgres && has a different meaning than OR
				$where = QueryBuilder::operatorsToStandardSyntax($where);
			}
		}

		try {
			$st = $this->prepare("DELETE FROM {$table}" . (!is_null($where) ? " WHERE {$where}" : ''));
			if(!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return $st->rowCount() > 0;
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		try {
			//input params
			$procedure_in_params = '';
			foreach ($inParams as $key => $value) {
				$procedure_in_params .= ":$key, ";
			}
			$procedure_in_params = rtrim($procedure_in_params, ', ');
			//output params
			$procedure_out_params = rtrim(array_reduce($outParams, function($total, $value) {
				return $total .= ":$value, ";
			}, ''), ', ');
			
			$st = $this->prepare($this->constructProcedureString($name, $procedure_in_params, $procedure_out_params));
			
			if(!self::bindParams($inParams, $st)) throw new UnexpectedValueException('Cannot bind parameters');

			$outResult = [];
			self::bindOutParams($outParams, $st, $outResult);
			if(!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return count($outParams) > 0 
				? $outResult 
				: self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public static function fetch($st, int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		if (($fetchMode === DBFactory::FETCH_COLUMN && is_int($fetchModeParam)) || ($fetchMode & DBFactory::FETCH_CLASS && is_string($fetchModeParam))) {
			return $fetchMode & DBFactory::FETCH_PROPS_LATE
				? $st->fetchAll($fetchMode, $fetchModeParam, $fetchPropsLateParams)
				: $st->fetchAll($fetchMode, $fetchModeParam);
		} else {
			return $st->fetchAll($fetchMode);
		}
	}

	public static function bindParams(array &$params, &$st = null): bool
	{
		foreach ($params as $key => $value) {
			$varType = is_null($value) ? self::PARAM_NULL : (is_bool($value) ? self::PARAM_BOOL : (is_int($value) ? self::PARAM_INT : self::PARAM_STR));
            if (!$st->bindValue(":$key", $value, $varType)) {
                return false;
			}
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

		if (is_null($connect_data_name)) throw new BadMethodCallException("Missing paramters");

		$connect_data_value = $params[$connect_data_name];
		$tns = preg_replace(
			"/\n\r|\n|\r|\n\r|\t|\s/",
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

		if (is_null($procedure_string)) throw new \Exception('Requested driver still not supported');

		return str_replace(['###name###', '###params###'], [$name, $parameters_string], $procedure_string);
	}
}
