<?php
namespace Extensions;

use \MongoDB\Client as MongoDB;
use \MongoDB\BSON as BSON;
use \MongoLog;

class MongoExtended extends MongoDB {
    private $dbName;
    
    /**
     * opens connection with db during object creation and set attributes depending on main configurations
     * @param   string  $host           db host
     * @param   int     $port           host port
     * @param   string  $user           username
     * @param   string  $pass           password
     * @param   string  $dbName         db name
     */
    public function __construct(string $host, int $port, string $user, string $pass, string $dbName) {
        parent::__construct("mongodb://$user:$pass@$host:$port/$dbName");
        //TODO da verificare se esiste ancora MongoLog
        if(DEBUG_MODE){
            MongoLog::setLevel(MongoLog::ALL);
            MongoLog::setModule(MongoLog::ALL);
        }

        $this->dbName = $dbName;
    }

    /**
     * execute select query
     * @param   string  $collection     collection name
     * @param   array   $search         query text with placeholders
     * @param   array   $options        assoc array with placeholder's name and relative values
     * @return  mixed                   response array or error message
     */
    public function select(string $collection, array $search, array $options = []) {
        try {
            foreach ($search as $param) {
                $param = filter_var($param);
            }

            return $this->{$this->dbName}->{$collection}
                ->find($search, $options)
                ->toArray();
        } catch (\MongoDB\Driver\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Error while connecting to db';
        } catch (\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute insert query
     * @param   string  $collection     collection name
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @return  mixed                   new row id or error message
     */
    public function insert(string $collection, array $params) {
        try {
            foreach ($params as $param) {
                $param = filter_var($param);
            }

            return $this->{$this->dbName}->{$collection}
                ->insertOne($params)
                ->getInsertedId()['oid'];
        } catch (\MongoDB\Driver\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Error while connecting to db';
        } catch (\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute update query. Where is required, no massive update permitted
     * @param   string  $collection     collection name
     * @param   array   $params         assoc array with placeholder's name and relative values
     * @param   array   $where          where condition. no placeholders permitted
     * @return  mixed                   correct query execution confirm as boolean or error message
     */
    public function update(string $collection, array $params, array $where) {
        try {
            foreach ($params as $param) {
                $param = filter_var($param);
            }

            foreach ($where as $param) {
                $param = filter_var($param);
            }

            return $this->{$this->dbName}->{$collection}
                ->updateMany($where, [ '$set' => $params], ['upsert' => FALSE])
                ->getModifiedCount();
        } catch (\MongoDB\Driver\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Error while connecting to db';
        } catch (\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Internal server error';
        }
    }

    /**
     * execute delete query. Where is required, no massive delete permitted
     * @param   string  $collection     collection name
     * @param   array   $where          where condition with placeholders
     * @return  mixed                   correct query execution confirm as boolean or error message
     */
    public function delete(string $collection, array $where) {
        try {
            foreach ($where as $param) {
                $param = filter_var($param);
            }

            $result = $this->{$this->dbName}->{$collection}
                ->deleteMany($where);

            return $result->getDeletedCount() ? TRUE : FALSE;
        } catch (\MongoDB\Driver\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Error while connecting to db';
        } catch (\Exception $e) {
            return DEBUG_MODE ? $e->getMessage() : 'Internal server error';
        }
    }
}
