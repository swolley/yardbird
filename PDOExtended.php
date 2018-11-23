<?php
namespace Api\Core;

use \PDO;

class PDOExtended extends PDO
{
    /**
     * opens connection with db dureing object creation and set attributes depending on main configurations
     */
    public function __construct()
    {
        $init_arr = array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'");
        parent::__construct(DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, $init_arr);
        if (DEBUG_MODE) {
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
    public function select(string $query, $params = [], $fetch_mode = PDO::FETCH_ASSOC)
    {
        try {
            ksort($params);
            $st = $this->prepare($query);
            foreach ($params as $key => $value) {
                $st->bindValue("$key", $value);
            }
            $st->execute();

            return $st->fetchAll($fetch_mode);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * execute insert query
     * @param   string  $table          table name
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @param   boolean $ignore         performes an 'insert ignore' query
     * @return  mixed                   new row id or error message
     */
    public function insert($table, $params, $ignore = false)
    {
        try {
            ksort($params);
            $keys = implode(',', array_keys($params));
            $values = ':' . implode(',:', array_keys($params));

            $this->beginTransaction();
            $st = $this->prepare("INSERT " . ($ignore ? "IGNORE " : "") . "INTO $table ($keys) VALUES ($values)");
            foreach ($params as $key => $value) {
                $st->bindValue(":$key", $value);
            }
            $st->execute();
            $inserted_id = $this->lastInsertId();
            $this->commit();

            return $inserted_id;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * execute update query. Where is required, no massive update permitted
     * @param   string  $table          table name
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @param   string  $where          where condition. no placeholders permitted
     * @return  mixed                   correct query execution confirm as boolean or error message
     */
    public function update($table, $params, $where)
    {
        try {
            ksort($params);
            $values = '';
            foreach ($params as $key => $value) {
                $values .= "`$key`=:$keys";
            }
            $field_details = rtrim($field_details, ',');

            $st = $this->prepare("UPDATE $table SET $values WHERE $where");
            foreach ($params as $key => $value) {
                $st->bindValue(":$key", $value);
            }

            return $st->execute();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * execute delete query. Where is required, no massive delete permitted
     * @param   string  $table          table name
     * @param   string  $where          where condition with placeholders
     * @param   array   $params         assoc array with placeholder's name and relative values for where condition
     * @return  mixed                   correct query execution confirm as boolean or error message
     */
    public function delete($table, $where, $params)
    {
        try {
            ksort($params);
            $st = $this->prepare("DELETE FROM $table WHERE $where");
            foreach ($params as $key => $value) {
                $st->bindValue("$key", $value);
            }

            return $st->execute();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * execute stored procedure
     * @param   string  $name           stored procedure name
     * @param   array   $params         (optional) assoc array with paramter's names and relative values
     * @param   int     $fetch_mode     (optional) PDO fetch mode. default = associative array
     * @return  mixed                   stored procedure result or error message
     */
    public function procedure($name, $params = [], $fetch_mode = PDO::FETCH_ASSOC)
    {
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

            return $st->fetchAll($fetch_mode);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
