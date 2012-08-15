<?php
namespace RecordsMan;

interface IDBAdapter {
    
    public function getTables($dbName = null);
    
    public function getTableColumns($tableName);
    
    public function fetchRow($sql, $params = null);
    
    public function fetchRows($sql, $params = null);
    
    public function fetchObject($sql, $params = null, $className = 'stdClass');
    
    public function fetchObjects($sql, $params = null, $className = 'stdClass');
    
    public function fetchColumnArray($sql, $params = null, $columnNum = 0);
    
    public function fetchSingleValue($sql, $params = null);                
    
    public function query($sql, $params = null);
    
    public function insert($table, $values);
    
    public function getLastInsertId();
    
    public function beginTransaction();
    
    public function commit();
    
    public function rollBack();
    
}

?>
