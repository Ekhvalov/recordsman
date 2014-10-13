<?php

namespace RecordsMan;

class MySqlAdapter implements IDBAdapter
{
    private static $_updPattern = '/^\s*update\s+`?\w+`?\s+set/Uis';
    private static $_insPattern = '/^\s*insert\s+into/Uis';

    protected $_host = 'localhost';
    protected $_user = 'root';
    protected $_pass = '';
    protected $_dbname = '';
    protected $_encodind = 'UTF8';
    protected $_connected = false;
    protected $_db = null;
    protected $_logging = false;
    protected $_queriesLog = [];
    protected $_readonly = false;

    public function __construct($host = 'localhost', $user = 'root', $pass = '', $db = '', $encoding = 'UTF8', $readonly = false) {
        if ($host && $user) {
            $this->connect($host, $user, $pass, $db, $encoding);
        }
        $this->_readonly = $readonly;
    }

    public function connect($host = '', $user = '', $pass = '', $db = '', $encoding = '') {
        $this->_host = $host ?: $this->_host;
        $this->_user = $user ?: $this->_user;
        $this->_pass = $pass ?: $this->_pass;
        $this->_dbname = $db ?: $this->_dbname;
        $this->_encoding = $encoding ?: $this->_encoding;
    }

    public function disconnect() {
        $this->_connected = false;
        $this->_db = null;
    }

    public function isConnected() {
        return $this->_connected;
    }

    public function getTables($dbName = null) {
        $sql = "SHOW TABLES" . ($dbName ? " FROM `{$dbName}`" : '');
        return $this->fetchColumnArray($sql);
    }

    public function getTableColumns($tableName) {
        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        //return $this->fetchColumnArray($sql);
        return $this->fetchRows($sql);
    }

    public function fetchRow($sql, $params = null, $assoc = true) {
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode($assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM);
        return $this->_queryResult($stmt, $params, true);
    }

    public function fetchRows($sql, $params = null, $assoc = true) {
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode($assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM);
        return $this->_queryResult($stmt, $params, false);
    }

    public function fetchObject($sql, $params = null, $className = 'stdClass') {
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $className);
        return $this->_queryResult($stmt, $params, true);
    }

    public function fetchObjects($sql, $params = null, $className = 'stdClass') {
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $className);
        return $this->_queryResult($stmt, $params, false);
    }

    public function fetchColumnArray($sql, $params = null, $columnNum = 0) {
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, $columnNum);
        return $this->_queryResult($stmt, $params, false);
    }

    public function fetchSingleValue($sql, $params = null) {
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
        return $this->_queryResult($stmt, $params, true);
    }

    public function query($sql, $params = null) {
        if ($this->_readonly && $this->_checkQueryForDataChanging($sql)) {
            throw new RecordsManException("Recordsman adapter: Connection opened in read-only mode");
        }
        $stmt = $this->_queryPrepare($sql, $params);
        if ($stmt->execute($params)) {
            return $stmt->rowCount();
        } else {
            $err = $stmt->errorInfo();
            throw new \RuntimeException($err[2] . "; Query was: {$sql}", /* $statement->errorCode() */111);
        }
    }

    public function insert($table, $values) {
        if (empty($values)) {
            $values = ['NULL'];
        }
        $keys = array_keys($values);
        $vals = array_values($values);
        $sql = "INSERT INTO `{$table}` ";
        if (!is_integer($keys[0])) {
            $keys = array_map(function($field) {
                return "`{$field}`";
            }, $keys);
            $sql.= '(' . rtrim(implode(',', $keys), ',') . ') ';
        }
        $sql.= "VALUES (" . rtrim(str_repeat('?,', count($vals)), ',') . ")";
        if ($this->query($sql, $vals)) {
            return $this->getLastInsertId();
        }
    }

    public function getLastInsertId() {
        return $this->_db->lastInsertId();
    }

    public function beginTransaction() {
        return $this->_db->beginTransaction();
    }

    public function commit() {
        return $this->_db->commit();
    }

    public function rollBack() {
        return $this->_db->rollBack();
    }

    public function getLog() {
        return $this->_queriesLog;
    }

    public function getLastQuery() {
        if (empty($this->_queriesLog)) {
            return '';
        }
        return $this->_queriesLog[count($this->_queriesLog) - 1]['query'];
    }

    public function logging($mode = true) {
        $this->_logging = !!$mode;
        return $this;
    }


    protected function _checkQueryForDataChanging($sql) {
        return preg_match(self::$_insPattern, $sql) || preg_match(self::$_updPattern, $sql);
    }

    protected function _realConnect() {
        $dsn = 'mysql:' . ($this->_dbname ? "dbname={$this->_dbname};" : '') . "host={$this->_host}";
        $this->_db = new \PDO($dsn, $this->_user, $this->_pass);
        if ($this->_encoding) {
            $this->_db->query("SET NAMES {$this->_encoding}");
        }
        $this->_connected = true;
    }

    protected function _queryPrepare($sql, $params = null) {
        $this->isConnected() or $this->_realConnect();
        $this->_log($sql, $params);
        return $this->_db->prepare($sql);
    }

    protected function _queryResult($statement, $params = null, $oneRow = false) {
        if ($statement->execute($params ? : [])) {
            return $oneRow ? $statement->fetch() : $statement->fetchAll();
        } else {
            $err = $statement->errorInfo();
            //$statement->debugDumpParams();
            throw new \RuntimeException("MySql error: {$err[2]}", /* $statement->errorCode() ?: */ 101);
        }
    }

    protected function _log($sql, $params = null) {
        if (!$this->_logging) {
            return false;
        }
        $this->_queriesLog[] = ['query' => $sql, 'params' => $params];
        return true;
    }
}
