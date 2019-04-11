<?php
namespace Swolley\Database;

use \PDO;

class PDOExtended extends PDO {
    /**
     * opens connection with db dureing object creation and set attributes depending on main configurations
     * @param   string  $type           db driver
     * @param   string  $host           db host
     * @param   int     $port           host port
     * @param   string  $user           username
     * @param   string  $pass           password
     * @param   string  $dbName         database name
     * @param   string  $charset        charset
     */
    public function __construct(string $type, string $host, int $port, string $user, string $pass, string $dbName, string $charset = 'UTF8') {
        if(!in_array($type, PDO::getAvailableDrivers())){
            throw "No database driver found";
        }

        $init_arr = array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'");
        parent::__construct("$type:host=$host;port=$port;dbname=$dbName;charset=$charset", $user, $pass, $init_arr);
        if (error_reporting() === E_ALL) {
            parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }

    /**
     * execute select query
     * @param   string  	$query          	query text with placeholders
     * @param   array   	$params         	assoc array with placeholder's name and relative values
	 * @param   int     	$fetchMode     		(optional) PDO fetch mode. default = associative array
     * @param	int|string	$fetchModeParam		(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
     * @return  mixed							response array or error message
     */
    public function select(string $query, array $params = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0) {
        try {
            ksort($params);
            $st = $this->prepare($query);
            foreach ($params as $key => $value) {
                $st->bindValue(":$key", $value);
            }
            $st->execute();

			if(($fetchMode === PDO::FETCH_COLUMN && is_int($fetchModeParam)) || ($fetchMode === PDO::FETCH_CLASS && is_string($fetchModeParam))) {
				return $st->fetchAll($fetchMode, $fetchModeParam);
			} else {
				return $st->fetchAll($fetchMode);
			}
        } catch (\PDOException $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
        } catch (\Exception $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute insert query
     * @param   string  $table          table name
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @param   boolean $ignore         performes an 'insert ignore' query
     * @return  mixed                   new row id or error message
     */
    public function insert(string $table, array $params, bool $ignore = false) {
        try {
            ksort($params);
            $keys = implode(',', array_keys($params));
            $values = ':' . implode(',:', array_keys($params));

            $this->beginTransaction();
            $st = $this->prepare('INSERT ' . ($ignore ? 'IGNORE ' : '') . "INTO $table ($keys) VALUES ($values)");
            foreach ($params as $key => $value) {
                $st->bindValue(":$key", $value);
            }
            $st->execute();
            $inserted_id = $this->lastInsertId();
            $this->commit();

            return $inserted_id;
        } catch (\PDOException $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
        } catch (\Exception $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute update query. Where is required, no massive update permitted
     * @param   string  $table          table name
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @param   string  $where          where condition. no placeholders permitted
     * @return  mixed                   correct query execution confirm as boolean or error message
     */
    public function update(string $table, array $params, string $where) {
        try {
            ksort($params);
            $values = '';
            foreach ($params as $key => $value) {
                $values .= "`$key`=:$key";
            }
            $field_details = rtrim($field_details, ',');

            $st = $this->prepare("UPDATE $table SET $values WHERE $where");
            foreach ($params as $key => $value) {
                $st->bindValue(":$key", $value);
            }

            return $st->execute();
        } catch (\PDOException $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
        } catch (\Exception $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute delete query. Where is required, no massive delete permitted
     * @param   string  $table          table name
     * @param   string  $where          where condition with placeholders
     * @param   array   $params         assoc array with placeholder's name and relative values for where condition
     * @return  mixed                   correct query execution confirm as boolean or error message
     */
    public function delete(string $table, string $where, array $params) {
        try {
            ksort($params);
            $st = $this->prepare("DELETE FROM $table WHERE $where");
            foreach ($params as $key => $value) {
                $st->bindValue("$key", $value);
            }

            return $st->execute();
        } catch (\PDOException $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
        } catch (\Exception $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute stored procedure
     * @param   string  	$name           	stored procedure name
     * @param   array   	$inParams         	(optional) assoc array with input paramter's names and relative values
	 * @param   array   	$outParams         	(optional) assoc array with output paramter's names and relative values
     * @param   int     	$fetchMode     		(optional) PDO fetch mode. default = associative array
	 * @param	int|string	$fetchModeParam		(optional) fetch mode param (ex. integer for FETCH_COLUMN, strin for FETCH_CLASS)
     * @return  mixed       	            	stored procedure result or error message
     */
    public function procedure(string $name, array $inParams = [], array $outParams = [], int $fetchMode = PDO::FETCH_ASSOC, $fetchModeParam = 0) {
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

            $st = $this->prepare(
				"CALL $name("
				. (count($inParams) > 0 ? $procedure_in_params : '')	//in params
				. (count($inParams)> 0 && count($outParams) > 0 ? ', ' : '')	//separator between in and out params
				. (count($outParams) > 0 ? $procedure_out_params : '')	//out params
				.");"
			);
            foreach ($inParams as $key => $value) {
                $st->bindValue(":$key", $value);
			}

			$outResult = [];
			foreach ($outParams as $value) {
				$outResult[$value] = null;
                $st->bindParam(":$value", $outResult[$value], PDO::PARAM_STR|PDO::PARAM_INPUT_OUTPUT, 400);
			}

			$st->execute();

			if(count($outParams) > 0){
				return $outResult;
			} elseif(($fetchMode === PDO::FETCH_COLUMN && is_int($fetchModeParam)) || ($fetchMode === PDO::FETCH_CLASS && is_string($fetchModeParam))) {
				return $st->fetchAll($fetchMode, $fetchModeParam);
			} else {
				return $st->fetchAll($fetchMode);
			}
        } catch (\PDOException $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
        } catch (\Exception $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
        }
    }
}
