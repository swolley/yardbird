<?php
namespace Swolley\YardBird\Drivers;

use Swolley\YardBird\Connection;
use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Interfaces\IRelationalConnectable;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Utils\QueryBuilder;
use Swolley\YardBird\Interfaces\TraitDatabase;

class Oci implements IRelationalConnectable
{
	use TraitDatabase;

	/**
	 * @var	resource	$_db	db connection
	 */
	private $_db;

	/**
	 * @param	array	$params	connection parameters
	 * @param	bool	$debugMode	debug mode
	 */
	public function __construct(array $params, bool $debugMode = false)
	{
		$parsed_params = self::validateConnectionParams($params);
		$this->setInfo($params, $debugMode);
		
		try {
			$connect_data_name = isset($parsed_params['sid']) ? 'sid' : (isset($parsed_params['serviceName']) ? 'serviceName' : null);
			if ($connect_data_name === null) throw new BadMethodCallException("Missing paramters");

			$connect_data_value = $params[$connect_data_name];
			$connection_string = mb_ereg_replace("/\n|\r|\n\r|\t/", '', "(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = {$parsed_params['host']})(PORT = {$parsed_params['port']}))) (CONNECT_DATA = (" . strtoupper(mb_ereg_replace('/(?<!^)[A-Z]/', '_$0', $connect_data_name)) . ' = ' . $connect_data_value	. ")))");
			$this->_db = oci_connect(...[ $parsed_params['user'], $parsed_params['password'], $connection_string ]);
			oci_internal_debug($debugMode);
		} catch(\Throwable $e) {
			throw new ConnectionException('Error while connecting to db');
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
			$params['port'] = 1521;
		}

		//default charset
		if (!isset($params['charset'])) {
			$params['charset'] = 'UTF8';
		}

		/////////////////////////////////////////////////////////////
		if ((!isset($params['sid']) || empty($params['sid'])) && (!isset($params['serviceName']) || empty($params['serviceName']))) {
			throw new BadMethodCallException("sid or serviceName must be specified");
		}

		return $params;
	}

	public function sql(string $query, $params = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$query = Utils::trimQueryString($query);
		$params = Utils::castToArray($params);
		//TODO add the function developed for mysqli
		$sth = oci_parse($this->_db, $query);
		
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if (!oci_execute($sth)) {
			$error = oci_error($sth);
			throw new QueryException($error['message'], $error['code']);
		}

		$response = mb_ereg_match('/^update|^insert|^delete/i', $query) === 1 ?: self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		oci_free_statement($sth);
		
		return $response;
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null, int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$sth = oci_parse($this->_db, 'SELECT ' . QueryBuilder::fieldsToSql($fields) . " FROM `$table` " . QueryBuilder::joinsToSql($join) . ' ' . QueryBuilder::whereToSql($where) . ' ' . QueryBuilder::orderByToSql($orderBy) . ' ' . QueryBuilder::limitToSql($limit));

		if(!empty($where) && !self::bindParams($where, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if (!oci_execute($sth)) {
			$error = oci_error($sth);
			throw new QueryException($error['message'], $error['code']);
		}

		$response = self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		oci_free_statement($sth);

		return $response;
	}

	public function insert(string $table, $params, bool $ignore = false)
	{
		$params = Utils::castToArray($params);
		$keys = implode(',', array_keys($params));
		$values = ':' . implode(', :', array_keys($params));
		$sth = oci_parse($this->_db, "BEGIN INSERT INTO `$table` ($keys) VALUES ($values)" . ($ignore ? ' EXCEPTION WHEN dup_val_on_index THEN null' : '') . '; END; RETURNING RowId INTO :last_inserted_id');
		
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		$inserted_id = null;
		self:: bindOutParams($sth, ":last_inserted_id", $inserted_id);
		if (!oci_execute($sth)) {
			$error = oci_error($sth);
			throw new QueryException($error['message'], $error['code']);
		}

		$response = oci_commit($this->_db);
		if (!$response) {
			$error = oci_error($this->_db);
			throw new OCIException($error['message'], $error['code']);
		}

		oci_free_statement($sth);
		
		return $inserted_id;
	}

	public function update(string $table, $params, $where = null): bool
	{
		$params = Utils::castToArray($params);
		
		if($where !== null && !is_string($where)) throw new UnexpectedValueException('$where param must be of type string');
		//TODO how to bind where clause?
		$values = QueryBuilder::valuesListToSql($params);
		
		$sth = oci_parse($this->_db, "UPDATE `{$table}` SET {$values}" . ($where !== null ? " WHERE {$where}" : ''));
		
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if (!oci_execute($sth)) {
			$error = oci_error($sth);
			throw new QueryException($error['message'], $error['code']);
		}

		oci_free_statement($sth);
		
		return true;
	}

	public function delete(string $table, $where = null, array $params = null): bool
	{
		if($where !== null && !is_string($where)) throw new UnexpectedValueException('$where param must be of type string');

		$sth = oci_parse($this->_db, "DELETE FROM `$table`" . ($where !== null ? " WHERE $where" : ''));
		
		if(!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');
		if (!oci_execute($sth)) {
			$error = oci_error($sth);
			throw new QueryException($error['message'], $error['code']);
		}

		oci_free_statement($sth);
		
		return true;
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = Connection::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		//input params
		$procedure_in_params = '';
		foreach ($inParams as $key => $value) {
			$procedure_in_params .= ":$key, ";
		}
		$procedure_in_params = rtrim($procedure_in_params, ', ');

		//output params
		$procedure_out_params = '';
		foreach ($outParams as $value) {
			$procedure_out_params .= ":$value, ";
		}
		$procedure_out_params = rtrim($procedure_out_params, ', ');

		$sth = oci_parse(
			$this->_db,
			"BEGIN $name("
				. (count($inParams) > 0 ? $procedure_in_params : '')	//in params
				. (count($inParams) > 0 && count($outParams) > 0 ? ', ' : '')	//separator between in and out params
				. (count($outParams) > 0 ? $procedure_out_params : '')	//out params
			. "); END;"
		);
		
		if(!self::bindParams($inParams, $sth)) throw new UnexpectedValueException('Cannot bind parameters');

		$outResult = [];
		self::bindOutParams($sth, $outParams, $outResult[$value]);
		if (!oci_execute($sth)) {
			$error = oci_error($sth);
			throw new QueryException($error['message'], $error['code']);
		}

		if (count($outParams) > 0) return $outResult;

		$response = self::fetch($sth, $fetchMode, $fetchModeParam, $fetchPropsLateParams);
		oci_free_statement($sth);

		return $response;
	}

	public function showTables(): array
	{
		//TODO tested only on mysql. to do on other drivers
		return array_map(function ($table) {
			return $table['TNAME'];
		}, $this->sql('SELECT * FROM tab'));
	}

	public function showColumns($tables): array
	{
		if (is_string($tables)) {
			$tables = [$tables];
		} elseif (!is_array($tables)) {
			throw new UnexpectedValueException('Table name must be string or array of strings');
		}

		$columns = [];
		foreach ($tables as $table) {
			//TODO actually only for mysql
			$cur = $this->sql("SELECT * FROM user_tab_cols WHERE table_name = '$table'");
			$columns[$table] = array_map(function ($column) {
				$column_name = $column['COLUMN_NAME'];
				$column_data = [ 
					'type' => strtolower($column['DATA_TYPE']),
					'nullable' => $column['NULLABLE'] === 'Y',
					'default' => $column['DATA_DEFAULT']
				];

				return [$column_name => $column_data];
			}, $cur);
		}

		$found_tables = count($columns);
		return $found_tables > 1 || $found_tables === 0 ? $columns : $columns[0];
	}

	public static function fetch($sth, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array
	{
		$response = [];
		if ($fetchMode === Connection::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = oci_fetch_row($sth)[$fetchModeParam] !== false) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & Connection::FETCH_CLASS && is_string($fetchModeParam)) {
			while ($row = oci_fetch_assoc($sth) !== false) {
				array_push($response, new $fetchModeParam(...$row));
			}
		} else {
			while ($row = oci_fetch_assoc($sth) !== false) {
				array_push($response, $row);
			}
		}

		return $response;
	}

	public static function bindParams(array &$params, &$sth = null): bool
	{
		//TODO to test if query cant be read from statement
		foreach ($params as $key => $value) {
            if (!oci_bind_by_name($sth, ":$key", $value)) {
                return false;
			}
        }

		return true;
	}

	public static function bindOutParams(&$params, &$sth, &$outResult, int $maxLength = 40000): void
	{
		if (is_array($params) && is_array($outResult)) {
			foreach ($params as $value) {
				$outResult[$value] = null;
				if (!oci_bind_by_name($sth, ":$value", $outResult[$value], $maxLength)) {
					throw new UnexpectedValueException('Cannot bind parameter value');
				}
			}
		} elseif (is_string($params)) {
			$outResult = null;
			if (!oci_bind_by_name($sth, ":$params", $outResult, $maxLength)) {
				throw new \Exception('Cannot bind parameter value');
			}
		} else {
			throw new BadMethodCallException('$params and $outResult must have same type');
		}
	}
}
