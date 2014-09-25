<?php
namespace RecordsMan;

trait TExternalFields
{
    private static $_externalFields = [];
    private $_externalFieldsCache = [];
    private $_externalFieldsChanged = [];
    private $_parentId = 'parent_id';

    public static function addExternalField($fieldName, $tableName, $fieldKey = null) {
        self::$_externalFields[$fieldName]['table'] = $tableName;
        self::$_externalFields[$fieldName]['fieldKey'] = $fieldKey ?: $fieldName;
        self::addProperty($fieldName, _createGetter($fieldName), _createSetter($fieldName));
    }

    public function saveExternalFields() {
        foreach ($this->_externalFieldsChanged as $tableName => $keysValues) {
            $sql = $this->_getInsertSql($tableName, array_keys($keysValues));
            /** @var Record|TExternalFields $this */
            $params = [":{$this->_parentId}" => $this->id];
            foreach ($keysValues as $placeholder => $value) {
                $params[":{$placeholder}"] = $value;
            }
            Record::getAdapter()->query($sql, $params);
            unset($this->_externalFieldsChanged[$tableName]);
        }
    }

    private function _getInsertSql($tableName, $colNames) {
        $keys = implode(',', array_map(function($colName) {
            return "`{$colName}`";
        }, $colNames));
        $placeholders = implode(',', array_map(function($placeholder) {
            return ":{$placeholder}";
        }, $colNames));
        $onDuplicate = implode(',', array_map(function($colName) {
            return "`{$colName}`=:{$colName}";
        }, $colNames));
        $sql = "INSERT INTO `{$tableName}` (`{$this->_parentId}`,{$keys}) VALUES (:{$this->_parentId},{$placeholders}) ";
        $sql.= "ON DUPLICATE KEY UPDATE {$onDuplicate};";
        return $sql;
    }

    private function _getTableName($fieldName) {
        return self::$_externalFields[$fieldName]['table'];
    }

    private function _getFieldKey($fieldName) {
        return self::$_externalFields[$fieldName]['fieldKey'];
    }

}

function _createGetter($fieldName) {
    return function() use ($fieldName) {
        /** @var Record|TExternalFields $this */
        if (!isset($this->_externalFieldsCache[$fieldName])) {
            $tableName = self::$_externalFields[$fieldName]['table'];
            $fieldKey = self::$_externalFields[$fieldName]['fieldKey'];
            $sql = "SELECT `{$fieldKey}` FROM `{$tableName}` WHERE `parent_id`=?";
            $this->_externalFieldsCache[$fieldName] = Record::getAdapter()
                ->fetchSingleValue($sql, [$this->id]);
        }
        return $this->_externalFieldsCache[$fieldName];
    };
}

function _createSetter($fieldName) {
    return function($value) use ($fieldName) {
        /** @var $this Record|TExternalFields */
        $this->_externalFieldsCache[$fieldName] = $value;
        $this->_externalFieldsChanged[$this->_getTableName($fieldName)][$this->_getFieldKey($fieldName)] = $value;
        return $value;
    };
}