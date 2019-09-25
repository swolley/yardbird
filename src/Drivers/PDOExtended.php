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

		try {
			parent::__construct(...self::composeConnectionParams($params));
			if (error_reporting() === E_ALL) {
				parent::setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
			}
			$this->_debugMode = $debugMode;
		} catch (\PDOException $e) {
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

	/**
	 * execute inline or complex queries query
	 * @param 	string  		$query          			query text with placeholders
	 * @param 	array|object  	$params         			assoc array with placeholder's name and relative values
	 * @param 	int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string		$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string		$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return	mixed										response array or error message
	 */
	public function sql(string $query, $params = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$params = Utils::castToArray($params);
		$query = Utils::trimQueryString($query);
		if ($this->getAttribute(self::ATTR_DRIVER_NAME) !== 'oci') {
			//because in postgres && has a different meaning than OR
			$query = QueryBuilder::operatorsToStandardSyntax($query);
		}

		//TODO add the function developed for mysqli

		try {
			//ksort($params);
			$st = $this->prepare($query);
			if (!self::bindParams($params, $st)) {
				throw new UnexpectedValueException('Cannot bind parameters');
			}
			if (!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return preg_match('/^update|^insert|^delete/i', $query) === 1 ? $st->rowCount() > 0 : self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * with sql drivers this is a very simple and limited SELECT query builder whit list of fields and AND-separated where clauses
	 * @param   string  		$table      				table name
	 * @param   array			$params     				assoc array with columns'name 
	 * @param   array			$where     					string query part or assoc array with placeholder's name and relative values. Logical separator between elements will be AND
	 * @param	array			$join						joins array
	 * @param 	int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string		$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string		$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return	mixed										response array or error message
	 */
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

			if (!self::bindParams($where, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if (!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * execute insert query
	 * @param   string  		$table      table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   boolean 		$ignore		performes an 'insert ignore' query
	 * @return  int|string|bool            	new row id if key is autoincremental or boolean
	 */
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
			if (!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if (!$st->execute()) {
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

	/**
	 * execute update query. Where is required, no massive update permitted
	 * @param   string  		$table	    table name
	 * @param   array|object	$params     assoc array with placeholder's name and relative values
	 * @param   string|array  	$where      where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
	 * @return  bool	                   	correct query execution confirm as boolean or error message
	 */
	public function update(string $table, $params, $where = null): bool
	{
		$params = Utils::castToArray($params);
		if (!is_null($where) && gettype($where) !== 'string') {
			throw new UnexpectedValueException('$where param must be of type string');
		} elseif (!is_null($where)) {
			if ($this->getAttribute(self::ATTR_DRIVER_NAME) !== 'oci') {
				//because in postgres && has a different meaning than OR
				$where = QueryBuilder::operatorsToStandardSyntax($where);
			}
		}
		//TODO how to bind where clause?
		try {
			$values = QueryBuilder::valuesListToSql($params);

			$st = $this->prepare("UPDATE `{$table}` SET {$values}" . (!is_null($where) ? " WHERE {$where}" : ''));

			if (!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if (!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return $st->rowCount() > 0;
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * execute delete query. Where is required, no massive delete permitted
	 * @param   string  		$table		table name
	 * @param   string|array  	$where		where condition (string for Relational Dbs, array for Mongo). no placeholders permitted
	 * @param   array   		$params		assoc array with placeholder's name and relative values for where condition
	 * @return  bool						correct query execution confirm as boolean or error message
	 */
	public function delete(string $table, $where = null, array $params = null): bool
	{
		if (!is_null($where) && gettype($where) !== 'string') {
			throw new UnexpectedValueException('$where param must be of type string');
		} elseif (!is_null($where)) {
			if ($this->getAttribute(self::ATTR_DRIVER_NAME) !== 'oci') {
				//because in postgres && has a different meaning than OR
				$where = QueryBuilder::operatorsToStandardSyntax($where);
			}
		}

		try {
			$st = $this->prepare("DELETE FROM {$table}" . (!is_null($where) ? " WHERE {$where}" : ''));
			if (!self::bindParams($params, $st)) throw new UnexpectedValueException('Cannot bind parameters');
			if (!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return $st->rowCount() > 0;
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * execute procedure call.
	 * @param  string		$table          			procedure name
	 * @param  array	  	$inParams       			array of input parameters
	 * @param  array	  	$outParams      			array of output parameters
	 * @param  int     		$fetchMode     				(optional) PDO fetch mode. default = associative array
	 * @param	int|string	$fetchModeParam				(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
	 * @param	int|string	$fetchModePropsLateParams	(optional) fetch mode param to class contructor
	 * @return mixed									response array or error message
	 */
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
			$procedure_out_params = rtrim(array_reduce($outParams, function ($total, $value) {
				return $total .= ":$value, ";
			}, ''), ', ');

			$st = $this->prepare($this->constructProcedureString($name, $procedure_in_params, $procedure_out_params));

			if (!self::bindParams($inParams, $st)) throw new UnexpectedValueException('Cannot bind parameters');

			$outResult = [];
			self::bindOutParams($outParams, $st, $outResult);
			if (!$st->execute()) {
				$error = $st->errorInfo();
				throw new QueryException("{$error[0]}: {$error[2]}" . ($this->_debugMode ? PHP_EOL . $st->debugDumpParams() : ''), $error[0]);
			}

			return count($outParams) > 0 ? $outResult : self::fetch($st, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		} catch (\PDOException $e) {
			throw new QueryException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function showTables(): array
	{
		$driver = $this->getAttribute(self::ATTR_DRIVER_NAME);
		return array_map(function ($table) use ($driver) {
			switch($driver) {
				case 'mysql':
					return array_values($table)[0];
				case 'oci':
					return $table['TNAME'];
				default:
					throw new \Exception('Requested driver still not supported');
			}
		}, $this->sql($this->constructShowTableString($driver)));
	}

	public function showColumns($tables): array
	{
		$type = gettype($tables);
		if ($type === 'string') {
			$tables = [$tables];
		} elseif ($type !== 'array') {
			throw new UnexpectedValueException('Table name must be string or array of strings');
		}

		$driver = $this->getAttribute(self::ATTR_DRIVER_NAME);
		$columns = [];
		foreach ($tables as $table) {
			$cur = $this->sql($this->constructShowColumnsString($driver, $table));
			$columns[$table] = array_map(function ($column) use($driver) {
				$column_name = null;
				$column_data = null;

				switch($driver) {
					case 'mysql':
						$column_name = $column['Field'];
						$column_data = [ 
							'type' => strpos($column['Type'], 'char') !== false || strpos($column['Type'], 'text') !== false ? 'string' : preg_replace("/int|year|month/", 'integer', preg_replace("/\(|\)|\\d|unsigned|big|small|tiny|\\s/i", '', strtolower($column['Type']))),
							'nullable' => $column['Null'] === 'YES',
							'default' => $column['Default']
						];
						break;
					case 'oci':
						$column_name = $column['COLUMN_NAME'];
						$column_data = [ 
							'type' => $column['DATA_TYPE'] === 'NUMBER' ? ($column['DATA_SCALE'] > 0 ? 'float' : 'integer') : (strpos($column['DATA_TYPE'], 'CHAR') !== false ? 'string' : strtolower($column['DATA_TYPE'])),
							'nullable' => $column['NULLABLE'] === 'Y',
							'default' => $column['DATA_DEFAULT']
						];
				}

				if(!isset($column_name, $column_data)) {
					throw new \Exception('Requested driver still not supported');
				}

				return [$column_name => $column_data];
			}, $cur);
		}

		$found_tables = count($columns);
		return $found_tables > 1 || $found_tables === 0 ? $columns : $columns[0];
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
	 * @throws	\Exception	driver still not supported
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

	/**
	 * @param	string	$driver	driver name
	 * @return	string	driver correct query string
	 * @throws	\Exception	driver still not supported
	 */
	protected function constructShowTableString(string $driver): string
	{
		$query = null;
		switch ($driver) {
			case 'mysql':
				$query = 'SHOW TABLES';
				break;
			case 'oci':
				$query = "SELECT * FROM tab WHERE  TNAME NOT LIKE 'BIN$%'";
				break;
			case 'mssql':
				$query = 'SELECT Distinct TABLE_NAME FROM information_schema.TABLES';
				break;
			case 'pgsql':
				$query = "SELECT * FROM pg_catalog.pg_tables WHERE table_schema = 'public'";
				break;
		}

		if (is_null($query)) throw new \Exception('Requested driver still not supported');

		return $query;
	}

	protected function constructShowColumnsString(string $driver, string $tableName): string
	{
		$query = null;
		switch ($driver) {
			case 'mysql':
				$query = "SHOW COLUMNS FROM $tableName";
				break;
			case 'oci':
				$query = "SELECT * FROM user_tab_cols WHERE table_name = '$tableName'";
				break;
		}

		if (is_null($query)) throw new \Exception('Requested driver still not supported');

		return $query;
	}
}
