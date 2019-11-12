<?php

namespace Swolley\YardBird\Drivers;

use Swolley\YardBird\Utils\Utils;
use Swolley\YardBird\Interfaces\IRelationalConnectable;
use Swolley\YardBird\Interfaces\AbstractResult;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Utils\QueryBuilder;
use Swolley\YardBird\Interfaces\TraitDatabase;
use Swolley\YardBird\Results\OciResult;

class Oci implements IRelationalConnectable
{
	use TraitDatabase;
	private $_inTransaction = false;

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
			$connect_data_name = isset($parsed_params['sid']) ? 'sid' : 'serviceName';
			$connect_data_value = $params[$connect_data_name];
			$connection_string = preg_replace("/\n|\r|\n\r|\t/", '', "(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = {$parsed_params['host']})(PORT = {$parsed_params['port']}))) (CONNECT_DATA = (" . strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $connect_data_name)) . ' = ' . $connect_data_value	. ")))");
			$this->_db = oci_connect($parsed_params['user'], $parsed_params['password'], $connection_string);
			oci_internal_debug($debugMode);
		} catch (\Throwable $e) {
			throw new ConnectionException('Error while connecting to db');
		}
	}

	public static function validateConnectionParams(array $params): array
	{
		if (!isset($params['host'], $params['user'], $params['password'], $params['dbName']) || empty($params['dbName']) || empty($params['host']) || empty($params['user']) || empty($params['password']) || (!isset($params['sid']) || empty($params['sid'])) && (!isset($params['serviceName']) || empty($params['serviceName'])))
			throw new BadMethodCallException("Empty or missing parameters");

		//defaults
		$params['port'] = $params['port'] ?? 1521;
		$params['charset'] = $params['charset'] ?? 'UTF8';
		return $params;
	}

	public function sql(string $query, $params = []): AbstractResult
	{
		$query = Utils::trimQueryString($query);
		$params = (array) $params;
		//TODO add the function developed for mysqli
		$sth = oci_parse($this->_db, $query);
		if (!self::bindParams($params, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!oci_execute($sth, $this->_inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
			$error = oci_error($sth);
			$this->rollback();
			throw new QueryException($error['message'], $error['code']);
		}

		$response = new OciResult($sth);
		if (!$this->_inTransaction) $this->commit();
		oci_free_statement($sth);
		return $response;
	}

	public function select(string $table, array $fields = [], array $where = [], array $join = [], array $orderBy = [], $limit = null): AbstractResult
	{
		$builder = new QueryBuilder;
		$sth = oci_parse($this->_db, 'SELECT ' . $builder->fieldsToSql($fields) . " FROM `$table` " . $builder->joinsToSql($join) . ' ' . $builder->whereToSql($where) . ' ' . $builder->orderByToSql($orderBy) . ' ' . $builder->limitToSql($limit));
		if (!empty($where) && !self::bindParams($where, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!oci_execute($sth, $this->_inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
			$error = oci_error($sth);
			$this->rollback();
			throw new QueryException($error['message'], $error['code']);
		}

		$response = new OciResult($sth);
		if (!$this->_inTransaction) $this->commit();
		oci_free_statement($sth);
		return $response;
	}

	public function insert(string $table, $params, bool $ignore = false): AbstractResult
	{
		$params = (array) $params;
		$keys = implode(',', array_keys($params));
		$values = ':' . implode(', :', array_keys($params));
		$sth = oci_parse($this->_db, "BEGIN INSERT INTO `$table` ($keys) VALUES ($values)" . ($ignore ? ' EXCEPTION WHEN dup_val_on_index THEN null' : '') . '; END; RETURNING RowId INTO :last_inserted_id');

		if (!self::bindParams($params, $sth)) throw new UnexpectedValueException('Cannot bind parameters');

		$inserted_id = null;
		self::bindOutParams($sth, ":last_inserted_id", $inserted_id);
		if (!oci_execute($sth, $this->_inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
			$error = oci_error($sth);
			$this->rollback();
			throw new QueryException($error['message'], $error['code']);
		}

		$response = new OciResult($sth);
		if (!$this->_inTransaction) $this->commit();
		oci_free_statement($sth);
		return $response;
	}

	public function update(string $table, $params, $where = null): AbstractResult
	{
		$params = (array) $params;

		if ($where !== null && !is_string($where)) throw new UnexpectedValueException('$where param must be of type string');
		//TODO how to bind where clause?
		$values = QueryBuilder::valuesListToSql($params);

		$sth = oci_parse($this->_db, "UPDATE `{$table}` SET {$values}" . ($where !== null ? " WHERE {$where}" : ''));
		if (!self::bindParams($params, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!oci_execute($sth, $this->_inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
			$error = oci_error($sth);
			$this->rollback();
			throw new QueryException($error['message'], $error['code']);
		}

		$response = new OciResult($sth);
		if (!$this->_inTransaction) $this->commit();
		oci_free_statement($sth);
		return $response;
	}

	public function delete(string $table, $where = null, array $params = null): AbstractResult
	{
		if ($where !== null && !is_string($where)) throw new UnexpectedValueException('$where param must be of type string');

		$sth = oci_parse($this->_db, "DELETE FROM `$table`" . ($where !== null ? " WHERE $where" : ''));
		if (!self::bindParams($params, $sth)) {
			throw new UnexpectedValueException('Cannot bind parameters');
		} elseif (!oci_execute($sth, $this->_inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
			$error = oci_error($sth);
			$this->rollback();
			throw new QueryException($error['message'], $error['code']);
		}

		$response = new OciResult($sth);
		if (!$this->_inTransaction) $this->commit();
		oci_free_statement($sth);
		return $response;
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [])
	{
		$procedure_in_params = rtrim(array_reduce($inParams, function ($sum, $key) {
			return $sum .= ":$key, ";
		}, ''), ', ');
		$procedure_out_params = rtrim(array_reduce($outParams, function ($sum, $value) {
			return $sum .= ":$value, ";
		}, ''), ', ');

		$sth = oci_parse(
			$this->_db,
			"BEGIN $name("
				. (count($inParams) > 0 ? $procedure_in_params : '')	//in params
				. (count($inParams) > 0 && count($outParams) > 0 ? ', ' : '')	//separator between in and out params
				. (count($outParams) > 0 ? $procedure_out_params : '')	//out params
				. "); END;"
		);

		if (!self::bindParams($inParams, $sth)) throw new UnexpectedValueException('Cannot bind parameters');

		$outResult = [];
		self::bindOutParams($sth, $outParams, $outResult);
		if (!oci_execute($sth, $this->_inTransaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS)) {
			$error = oci_error($sth);
			$this->rollback();
			throw new QueryException($error['message'], $error['code']);
		}

		if (count($outParams) > 0) return $outResult;
		$response = new OciResult($sth);

		if (!$this->_inTransaction) $this->commit();
		oci_free_statement($sth);
		return $response;
	}

	public function showTables(): array
	{
		//TODO tested only on mysql. to do on other drivers
		return array_map(function ($table) {
			return $table['TNAME'];
		}, $this->sql('SELECT * FROM tab')->fetch());
	}

	public function showColumns($tables)
	{
		if (is_string($tables)) {
			$tables = [$tables];
		} elseif (!is_array($tables)) {
			throw new UnexpectedValueException('Table name must be string or array of strings');
		}

		$columns = [];
		foreach ($tables as $table) {
			//TODO actually only for mysql
			$cur = $this->sql("SELECT * FROM user_tab_cols WHERE table_name = '$table'")->fetch();
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

		return $columns;
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
				if (!oci_bind_by_name($sth, ":$value", $outResult[$value], $maxLength)) throw new UnexpectedValueException('Cannot bind parameter value');
			}
		} elseif (is_string($params)) {
			$outResult = null;
			if (!oci_bind_by_name($sth, ":$params", $outResult, $maxLength)) throw new \Exception('Cannot bind parameter value');
		} else {
			throw new BadMethodCallException('$params and $outResult must have same type');
		}
	}

	public function transaction(): bool
	{
		$this->_inTransaction = true;
		return true;
	}

	public function commit(): bool
	{
		if ($this->_inTransaction) {
			$this->_inTransaction = false;
			if (!oci_commit($this->_db)) {
				$error = oci_error($this->_db);
				throw new OCIException($error['message'], $error['code']);
			}
			return true;
		}

		return false;
	}

	public function rollback(): bool
	{
		if ($this->_inTransaction) {
			$this->_inTransaction = false;
			return oci_rollback($this->_db);
		}

		return false;
	}
}
