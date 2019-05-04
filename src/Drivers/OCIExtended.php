<?php
namespace Swolley\Database\Drivers;

use Swolley\Database\DBFactory;
use Swolley\Database\Interfaces\IRelationalConnectable;
use Swolley\Database\Exceptions\QueryException;
use Swolley\Database\Exceptions\BadMethodCallException;
use Swolley\Database\Exceptions\UnexpectedValueException;

final class OCIExtended implements IRelationalConnectable
{
	use TraitUtils;

	private $db;

	public function __construct(array $params)
	{
		$params = self::validateConnectionParams($params);
		$this->db = oci_connect($params['user'], $params['password'], self::constructConnectionString($params));
	}

	public static function validateConnectionParams($params): array
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

	public static function constructConnectionString(array $params, array $init_Array = []): string
	{
		$connect_data_name = $params['sid'] ? 'sid' : ($params['serviceName'] ? 'serviceName' : null);

		if (is_null($connect_data_name)) {
			throw new BadMethodCallException("Missing paramters");
		}

		$connect_data_value = $params[$connect_data_name];

		return preg_replace(
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
	}

	public function sql(string $query, $params = [], int $fetchMode = BDFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$params = self::castParamsToArray($params);

		ksort($params);
		$st = oci_parse($this->db, $query);
		self::bindParams($params, $st);
		if (!oci_execute($st)) {
			$error = oci_error($st);
			throw new QueryException($error['message'], $error['code']);
		}

		$response = [];
		if ($fetchMode === BDFactory::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = oci_fetch_row($st)[$fetchModeParam] !== false) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & BDFactory::FETCH_CLASS && is_string($fetchModeParam)) {
			return new $fetchModeParam(...oci_fetch_assoc($st));
		} else {
			while ($row = oci_fetch_assoc($st) !== false) {
				array_push($response, $row);
			}
		}

		oci_free_statement($st);
		return $response;
	}

	public function select(string $table, array $fields = [], array $where = [], int $fetchMode = DBFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		$stringed_fields = join(', ', $fields);

		ksort($where);
		$values = '';
		foreach ($where as $key => $value) {
			$values .= "`$key`=:$key AND ";
		}
		$stringed_where = rtrim($values, 'AND ');

		$st = oci_parse($this->db, "SELECT {$stringed_fields} FROM {$table} WHERE {$stringed_where}");
		self::bindParams($where, $st);
		if (!oci_execute($st)) {
			$error = oci_error($st);
			throw new QueryException($error['message'], $error['code']);
		}

		$response = [];
		if ($fetchMode === BDFactory::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = oci_fetch_row($st)[$fetchModeParam] !== false) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & BDFactory::FETCH_CLASS && is_string($fetchModeParam)) {
			return new $fetchModeParam(...oci_fetch_assoc($st));
		} else {
			while ($row = oci_fetch_assoc($st) !== false) {
				array_push($response, $row);
			}
		}

		oci_free_statement($st);
		return $response;
	}

	public function insert(string $table, $params, bool $ignore = false)
	{
		$params = self::castParamsToArray($params);
		ksort($params);
		$keys = implode(',', array_keys($params));
		$values = ':' . implode(', :', array_keys($params));

		$st = oci_parse($this->db, "BEGIN INSERT INTO $table ($keys) VALUES ($values); EXCEPTION WHEN dup_val_on_index THEN null; END; RETURNING RowId INTO :last_inserted_id");
		self::bindParams($params, $st);
		$inserted_id = null;
		selff:bindOutParams($st, ":last_inserted_id", $inserted_id);
		if (!oci_execute($st)) {
			$error = oci_error($st);
			throw new QueryException($error['message'], $error['code']);
		}

		$r = oci_commit($this->db);
		if (!$r) {
			$error = oci_error($$this->db);
			throw new OCIException($error['message'], $error['code']);
		}

		oci_free_statement($st);
		return $inserted_id;
	}

	public function update(string $table, $params, string $where): bool
	{
		$params = self::castParamsToArray($params);

		ksort($params);
		$values = '';
		foreach ($params as $key => $value) {
			$values .= "`$key`=:$key";
		}
		$values = rtrim($values, ', ');

		$st = oci_parse($this->db, "UPDATE $table SET $values WHERE $where");
		self::bindParams($params, $st);

		if (!oci_execute($st)) {
			$error = oci_error($st);
			throw new QueryException($error['message'], $error['code']);
		}

		oci_free_statement($st);
		return true;
	}

	public function delete(string $table, string $where, array $params): bool
	{
		ksort($params);
		$st = oci_parse($this->db, "DELETE FROM $table WHERE $where");
		self::bindParams($params, $st);

		if (!oci_execute($st)) {
			$error = oci_error($st);
			throw new OCIException($error['message'], $error['code']);
		}

		oci_free_statement($st);
		return true;
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = BDFactory::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
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

		$st = oci_parse(
			$this->db,
			"BEGIN $name("
				. (count($inParams) > 0 ? $procedure_in_params : '')	//in params
				. (count($inParams) > 0 && count($outParams) > 0 ? ', ' : '')	//separator between in and out params
				. (count($outParams) > 0 ? $procedure_out_params : '')	//out params
				. "); END;"
		);
		self::bindParams($inParams, $st);
		$outResult = [];
		self::bindOutParams($st, $outParams, $outResult[$value]);
		if (!oci_execute($st)) {
			$error = oci_error($st);
			throw new QueryException($error['message'], $error['code']);
		}

		if (count($outParams) > 0) {
			return $outResult;
		}

		$response = [];
		if ($fetchMode === BDFactory::FETCH_COLUMN && is_int($fetchModeParam)) {
			while ($row = oci_fetch_row($st)[$fetchModeParam] !== false) {
				array_push($response, $row);
			}
		} elseif ($fetchMode & BDFactory::FETCH_CLASS && is_string($fetchModeParam)) {
			return new $fetchModeParam(...oci_fetch_assoc($st));
		} else {
			while ($row = oci_fetch_assoc($st) !== false) {
				array_push($response, $row);
			}
		}

		oci_free_statement($st);
		return $response;
	}

	public static function bindParams(array &$params, &$st = null)
	{
		foreach ($params as $key => $value) {
			if (!oci_bind_by_name($st, ":$key", $value)) {
				throw new UnexpectedValueException('Cannot bind parameter value');
			}
		}
	}

	public static function bindOutParams(&$params, &$st, &$outResult, int $maxLength = 40000)
	{
		if(gettype($params) === 'array' && gettype($outResult) === 'array') {
			foreach ($params as $value) {
				$outResult[$value] = null;
				if (!oci_bind_by_name($st, ":$value", $outResult[$value], $maxLength)) {
					throw new UnexpectedValueException('Cannot bind parameter value');
				}
			}	
		} elseif(gettype($params) === 'string') {
			$outResult = null;
			if (!oci_bind_by_name($st, ":$params", $outResult, $maxLength)) {
				throw new \Exception('Cannot bind parameter value');
			}
		} else {
			throw new BadMethodCallException('$params and $outResult must have same type');
		}
	}
}
