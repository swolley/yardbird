<?php
namespace Swolley\Database;

//require_once 'IConnectable.php';

class OCI
{
	const FETCH_COLUMN = 7;
	const FETCH_CLASS = 8;
	const FETCH_ASSOC = 2;
	const FETCH_PROPS_LATE = 1048576;
}

class OCIException extends \RuntimeException
{ 
}

final class OCIExtended implements IConnectable
{
	private $db;

	public function __construct(array $params)
	{
		$params = self::validateParams($params);
		$this->db = oci_connect($params['user'], $params['password'], self::constructConnectionString($params));
	}

	public static function validateParams($params): array
	{
		if (!isset($params['host'], $params['user'], $params['password'])) {
			throw new \BadMethodCallException("host, user, password are required");
		} elseif (empty($params['host']) || empty($params['user']) || empty($params['password'])) {
			throw new \UnexpectedValueException("host, user, password can't be empty");
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
			throw new \BadMethodCallException("sid or serviceName must be specified");
		}

		return $params;
	}

	public static function constructConnectionString(array $params, array $init_Array = []): string
	{
		$connect_data_name = $params['sid'] ? 'sid' : ($params['serviceName'] ? 'serviceName' : null);

		if (is_null($connect_data_name)) {
			throw new \BadMethodCallException("Missing paramters");
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

	public function query(string $query, array $params = [], int $fetchMode = OCI::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
	{
		try {
			ksort($params);
			$st = oci_parse($this->db, $query);
			foreach ($params as $key => $value) {
				if (!oci_bind_by_name($st, ":$key", $value)) {
					throw new \Exception('Cannot bind parameter value');
				}
			}
			if (!oci_execute($st)) {
				$error = oci_error($st);
				throw new OCIException($error['message'], $error['code']);
			}

			$response = [];
			if ($fetchMode === OCI::FETCH_COLUMN && is_int($fetchModeParam)) {
				while ($row = oci_fetch_row($st)[$fetchModeParam] !== false) {
					array_push($response, $row);
				}
			} elseif ($fetchMode & OCI::FETCH_CLASS && is_string($fetchModeParam)) {
				return new $fetchModeParam(...oci_fetch_assoc($st));
			} else {
				while ($row = oci_fetch_assoc($st) !== false) {
					array_push($response, $row);
				}
			}

			oci_free_statement($st);
			return $response;
		} catch (OCIException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	public function insert(string $table, array $params, bool $ignore = false)
	{
		try {
			ksort($params);
			$keys = implode(',', array_keys($params));
			$values = ':' . implode(', :', array_keys($params));

			$st = oci_parse($this->db, "BEGIN INSERT INTO $table ($keys) VALUES ($values); EXCEPTION WHEN dup_val_on_index THEN null; END; RETURNING RowId INTO :last_inserted_id");
			foreach ($params as $key => $value) {
				if (!oci_bind_by_name($st, ":$key", $value)) {
					throw new \Exception('Cannot bind parameter value');
				}
			}

			if (!oci_bind_by_name($st, ":last_inserted_id", $inserted_id, 4000)) {
				throw new \Exception('Cannot bind parameter value');
			}

			if (!oci_execute($st)) {
				$error = oci_error($st);
				throw new OCIException($error['message'], $error['code']);
			}

			$r = oci_commit($this->db);
			if (!$r) {
				$error = oci_error($$this->db);
				throw new OCIException($error['message'], $error['code']);
			}

			oci_free_statement($st);
			return $inserted_id;
		} catch (OCIException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	public function update(string $table, array $params, string $where): bool
	{
		try {
			ksort($params);
			$values = '';
			foreach ($params as $key => $value) {
				$values .= "`$key`=:$key";
			}
			$field_details = rtrim($field_details, ', ');

			$st = oci_parse($this->db, "UPDATE $table SET $values WHERE $where");
			foreach ($params as $key => $value) {
				if (!oci_bind_by_name($st, ":$key", $value)) {
					throw new \Exception('Cannot bind parameter value');
				}
			}

			if (!oci_execute($st)) {
				$error = oci_error($st);
				throw new OCIException($error['message'], $error['code']);
			}

			oci_free_statement($st);
			return true;
		} catch (OCIException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	public function delete(string $table, string $where, array $params): bool
	{
		try {
			ksort($params);
			$st = oci_parse($this->db, "DELETE FROM $table WHERE $where");
			foreach ($params as $key => $value) {
				if (!oci_bind_by_name($st, ":$key", $value)) {
					throw new \Exception('Cannot bind parameter value');
				}
			}

			if (!oci_execute($st)) {
				$error = oci_error($st);
				throw new OCIException($error['message'], $error['code']);
			}

			oci_free_statement($st);
			return true;
		} catch (OCIException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}

	public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = OCI::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = [])
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

			$st = oci_parse(
				$this->db,
				"BEGIN $name("
					. (count($inParams) > 0 ? $procedure_in_params : '')	//in params
					. (count($inParams) > 0 && count($outParams) > 0 ? ', ' : '')	//separator between in and out params
					. (count($outParams) > 0 ? $procedure_out_params : '')	//out params
					. "); END;"
			);
			foreach ($inParams as $key => $value) {
				if (!oci_bind_by_name($st, ":$key", $value)) {
					throw new \Exception('Cannot bind parameter value');
				}
			}

			$outResult = [];
			foreach ($outParams as $value) {
				$outResult[$value] = null;
				if (!oci_bind_by_name($st, ":$key", $outResult[$value], 40000)) {
					throw new \Exception('Cannot bind parameter value');
				}
			}

			if (!oci_execute($st)) {
				$error = oci_error($st);
				throw new OCIException($error['message'], $error['code']);
			}

			if (count($outParams) > 0) {
				return $outResult;
			}

			$response = [];
			if ($fetchMode === OCI::FETCH_COLUMN && is_int($fetchModeParam)) {
				while ($row = oci_fetch_row($st)[$fetchModeParam] !== false) {
					array_push($response, $row);
				}
			} elseif ($fetchMode & OCI::FETCH_CLASS && is_string($fetchModeParam)) {
				return new $fetchModeParam(...oci_fetch_assoc($st));
			} else {
				while ($row = oci_fetch_assoc($st) !== false) {
					array_push($response, $row);
				}
			}

			oci_free_statement($st);
			return $response;
		} catch (OCIException $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
		} catch (\Exception $e) {
			return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
		}
	}
}
