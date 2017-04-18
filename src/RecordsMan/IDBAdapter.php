<?php
namespace RecordsMan;

interface IDBAdapter
{
    /**
     * @param null|string $dbName
     * @return array
     */
    public function getTables($dbName = null);

    /**
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName);

    /**
     * @param string $sql
     * @param null $params
     * @param bool $assoc
     * @return mixed
     * @throws RecordsManException
     */
    public function fetchRow($sql, $params = null, $assoc = true);

    /**
     * @param string $sql
     * @param null $params
     * @param bool $assoc
     * @return array
     * @throws RecordsManException
     */
    public function fetchRows($sql, $params = null, $assoc = true);

    /**
     * @param string $sql
     * @param null $params
     * @param string $className
     * @return mixed
     * @throws RecordsManException
     */
    public function fetchObject($sql, $params = null, $className = 'stdClass');

    /**
     * @param string $sql
     * @param null $params
     * @param string $className
     * @return array
     * @throws RecordsManException
     */
    public function fetchObjects($sql, $params = null, $className = 'stdClass');

    /**
     * @param string $sql
     * @param null $params
     * @param int $columnNum
     * @return array
     * @throws RecordsManException
     */
    public function fetchColumnArray($sql, $params = null, $columnNum = 0);

    /**
     * @param string $sql
     * @param null $params
     * @return mixed
     * @throws RecordsManException
     */
    public function fetchSingleValue($sql, $params = null);

    /**
     * @param string $sql
     * @param null $params
     * @return int
     * @throws RecordsManException
     */
    public function query($sql, $params = null);

    /**
     * @param string $table
     * @param array $values
     * @return bool|string
     * @throws RecordsManException
     */
    public function insert($table, $values);

    /**
     * @return string
     */
    public function getLastInsertId();

    /**
     * @return bool
     * @throws RecordsManException
     */
    public function beginTransaction();

    /**
     * @return bool
     */
    public function inTransaction();

    /**
     * @throws RecordsManException
     */
    public function commit();

    /**
     * @throws RecordsManException
     */
    public function rollBack();

    public function disconnect();
}
