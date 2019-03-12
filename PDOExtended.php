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
     * @param   string  $query          query text with placeholders
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @param   int     $fetch_mode     (optional) PDO fetch mode. default = associative array
     * @return  mixed                   response array or error message
     */
    public function select(string $query, array $params = [], int $fetch_mode = PDO::FETCH_ASSOC, int $fetchColumnNumber = 0) {
        try {
            ksort($params);
            $st = $this->prepare($query);
            foreach ($params as $key => $value) {
                $st->bindValue("$key", $value);
            }
            $st->execute();

            return $fetch_mode === PDO::FETCH_COLUMN ? $st->fetchAll($fetch_mode, $fetchColumnNumber) : $st->fetchAll($fetch_mode);
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
     * @param   string  $name           	stored procedure name
     * @param   array   $params         	(optional) assoc array with paramter's names and relative values
     * @param   int     $fetch_mode     	(optional) PDO fetch mode. default = associative array
	 * @param	int		$fetchColumnNumber	(optional) rows' column to fetch
     * @return  mixed                   	stored procedure result or error message
     */
    public function procedure(string $name, array $params = [], int $fetch_mode = PDO::FETCH_ASSOC, int $fetchColumnNumber = 0) {
        try {
            //ksort($params);
            $procedure_params = '';
            foreach ($params as $key => $value) {
                $procedure_params .= ":$key,";
            }
            $procedure_params = rtrim($procedure_params, ',');

            $st = $this->prepare("CALL $name($procedure_params)");
            foreach ($params as $key => $value) {
                $st->bindValue(":$key", $value);
            }
            $st->execute();

            return $fetch_mode === PDO::FETCH_COLUMN ? $st->fetchAll($fetch_mode, $fetchColumnNumber) : $st->fetchAll($fetch_mode);
        } catch (\PDOException $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Error while querying db';
        } catch (\Exception $e) {
            return error_reporting() === E_ALL ? $e->getMessage() : 'Internal server error';
        }
    }
}
