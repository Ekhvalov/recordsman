<?php

namespace RecordsMan;

class MySQLAdapter implements IDBAdapter
{
    protected $_host = 'localhost';
    protected $_user = 'root';
    protected $_pass = '';
    protected $_dbname = '';
    protected $_encoding = 'UTF8';
    protected $_connected = false;
    /** @var \PDO $_db */
    protected $_db = null;
    protected $_logging = false;
    protected $_queriesLog = [];

    public function __construct(
        $host = 'localhost',
        $user = 'root',
        $pass = '',
        $db = '',
        $encoding = 'UTF8'
    ) {
        if ($host && $user) {
            $this->connect($host, $user, $pass, $db, $encoding);
        }
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

    /**
     * @return bool
     */
    public function isConnected() {
        return $this->_connected;
    }

    /**
     * @param null|string $dbName
     * @return mixed
     */
    public function getTables($dbName = null) {
        $sql = "SHOW TABLES" . ($dbName ? " FROM `{$dbName}`" : '');
        return $this->fetchColumnArray($sql);
    }

    /**
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName) {
        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        //return $this->fetchColumnArray($sql);
        return $this->fetchRows($sql);
    }

    /**
     * @param string $sql
     * @param null $params
     * @param bool $assoc
     * @return mixed
     */
    public function fetchRow($sql, $params = null, $assoc = true) {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode($assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM);
        return $this->_queryResult($stmt, $params, true);
    }

    /**
     * @param string $sql
     * @param null $params
     * @param bool $assoc
     * @return array
     */
    public function fetchRows($sql, $params = null, $assoc = true) {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode($assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM);
        return $this->_queryResult($stmt, $params, false);
    }

    /**
     * @param string $sql
     * @param null $params
     * @param string $className
     * @return mixed
     */
    public function fetchObject($sql, $params = null, $className = 'stdClass') {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $className);
        return $this->_queryResult($stmt, $params, true);
    }

    /**
     * @param string $sql
     * @param null $params
     * @param string $className
     * @return mixed
     */
    public function fetchObjects($sql, $params = null, $className = 'stdClass') {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, $className);
        return $this->_queryResult($stmt, $params, false);
    }

    /**
     * @param string $sql
     * @param null $params
     * @param int $columnNum
     * @return mixed
     */
    public function fetchColumnArray($sql, $params = null, $columnNum = 0) {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, $columnNum);
        return $this->_queryResult($stmt, $params, false);
    }

    /**
     * @param string $sql
     * @param null $params
     * @return mixed
     */
    public function fetchSingleValue($sql, $params = null) {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        $stmt->setFetchMode(\PDO::FETCH_COLUMN, 0);
        return $this->_queryResult($stmt, $params, true);
    }

    /**
     * @param $sql
     * @param null $params
     * @return int
     * @throws RecordsManException
     */
    public function query($sql, $params = null) {
        /** @var \PDOStatement $stmt */
        $stmt = $this->_queryPrepare($sql, $params);
        if ($stmt->execute($params)) {
            return $stmt->rowCount();
        } else {
            throw new RecordsManException("{$stmt->errorInfo()[2]}; Query was: {$sql}", 111);
        }
    }

    /**
     * @param $table
     * @param $values
     * @return bool|string
     * @throws RecordsManException
     */
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
        return ($this->query($sql, $vals)) ? $this->getLastInsertId() : false;
    }

    /**
     * @return string
     */
    public function getLastInsertId() {
        return $this->_db->lastInsertId();
    }

    /**
     * @return bool
     */
    public function beginTransaction() {
        return $this->_db->beginTransaction();
    }

    /**
     * @return bool
     */
    public function commit() {
        return $this->_db->commit();
    }

    /**
     * @return bool
     */
    public function rollBack() {
        return $this->_db->rollBack();
    }

    /**
     * @return array
     */
    public function getLog() {
        return $this->_queriesLog;
    }

    /**
     * @return string
     */
    public function getLastQuery() {
        if (empty($this->_queriesLog)) {
            return '';
        }
        return $this->_queriesLog[count($this->_queriesLog) - 1]['query'];
    }

    /**
     * @param bool $mode
     * @return MySQLAdapter
     */
    public function logging($mode = true) {
        $this->_logging = !!$mode;
        return $this;
    }

    protected function _realConnect() {
        $dsn = 'mysql:' . ($this->_dbname ? "dbname={$this->_dbname};" : '') . "host={$this->_host}";
        $this->_db = new \PDO($dsn, $this->_user, $this->_pass);
        if ($this->_encoding) {
            $this->_db->query("SET NAMES {$this->_encoding}");
        }
        $this->_connected = true;
    }

    /**
     * @param $sql
     * @param null $params
     * @return \PDOStatement
     */
    protected function _queryPrepare($sql, $params = null) {
        $this->isConnected() or $this->_realConnect();
        $this->_log($sql, $params);
        return $this->_db->prepare($sql);
    }

    /**
     * @param \PDOStatement $statement
     * @param null $params
     * @param bool $oneRow
     * @return mixed
     * @throws RecordsManException
     */
    protected function _queryResult($statement, $params = null, $oneRow = false) {
        if ($statement->execute($params ? : [])) {
            return $oneRow ? $statement->fetch() : $statement->fetchAll();
        } else {
            $err = $statement->errorInfo();
            throw new RecordsManException("MySql error: {$err[2]}", 101);
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
