<?php
namespace RecordsMan;

trait TExternalFields
{
    private static $_externalFields = [];
    private $_externalFieldsCache = [];
    private $_externalFieldsChanged = [];
    private $_parentId = 'parent_id';

    public static function externalFieldsInit() {
        self::addTrigger(self::SAVE, function(self $record) {
            $record->_saveExternalFields();
        });

        self::addTrigger(self::DELETED, function(self $record, $triggerName, $id) {
            $record->_deleteExternalFields($id);
        });
    }

    public static function addExternalField($fieldName, $tableName, $fieldKey = null) {
        self::$_externalFields[$fieldName]['table'] = $tableName;
        self::$_externalFields[$fieldName]['fieldKey'] = $fieldKey ?: $fieldName;
        self::addProperty($fieldName, _createGetter($fieldName), _createSetter($fieldName));
    }

    private function _saveExternalFields() {
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

    private function _deleteExternalFields($id) {
        foreach ($this->_getTableNames() as $tableName) {
            $this->_deleteExternalRow($tableName, $id);
        }
    }

    private function _getTableNames() {
        $tableNames = [];
        foreach (self::$_externalFields as $fieldName => $fieldData) {
            $tableNames[$this->_getTableName($fieldName)] = '';
        }
        return array_keys($tableNames);
    }

    private function _deleteExternalRow($tableName, $rowId) {
        $sql = "DELETE FROM `{$tableName}` WHERE `{$this->_parentId}`='{$rowId}' LIMIT 1";
        Record::getAdapter()->query($sql);
    }

}

function _createGetter($fieldName) {
    return function() use ($fieldName) {
        /** @var Record|TExternalFields $this */
        if (!isset($this->_externalFieldsCache[$fieldName])) {
            $tableName = self::$_externalFields[$fieldName]['table'];
            $fieldKey = self::$_externalFields[$fieldName]['fieldKey'];
            $sql = "SELECT `{$fieldKey}` FROM `{$tableName}` WHERE `parent_id`=?";
            $result = Record::getAdapter()->fetchSingleValue($sql, [$this->id]);
            $this->_externalFieldsCache[$fieldName] = ($result === false) ? null : $result;
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