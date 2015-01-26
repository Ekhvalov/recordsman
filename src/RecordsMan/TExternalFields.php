<?php
namespace RecordsMan;

trait TExternalFields
{
    private static $_fieldTable = [];
    private static $_tableForeignKey = [];
    private static $_initialized = false;
    private static $_foreignKey;

    private $_externalFieldsCache = [];
    private $_externalFieldsChanged = [];

    /**
     * @param string $fieldName Column name
     * @param string $tableName Table name
     * @param null|string $foreignKey if null then {class_name}_id
     */
    public static function addExternalField($fieldName, $tableName, $foreignKey = null) {
        if (!self::$_initialized) {
            self::_externalFieldsInit();
            self::$_initialized = true;
        }
        self::$_fieldTable[$fieldName] = $tableName;
        self::$_tableForeignKey[$tableName] = self::_getForeignKey($foreignKey);
        self::addProperty($fieldName, _createGetter($fieldName), _createSetter($fieldName));
    }

    private static function _externalFieldsInit() {
        self::addTrigger(self::SAVE, function(self $record) {
            /** @var Record|TExternalFields $record */
            if ($record->id) {
                $record->_saveExternalFields();
            }
        });
        self::addTrigger(self::SAVED, function(self $record) {
            $record->_saveExternalFields();
        });
        self::addTrigger(self::DELETED, function(self $record, $triggerName, $id) {
            $record->_deleteExternalFields($id);
        });
    }

    private static function _getForeignKey($foreignKey) {
        if (!is_null($foreignKey)) {
            return $foreignKey;
        }
        if (!isset(self::$_foreignKey)) {
            $foreignKey =  Helper::ucFirstToUnderscore(Helper::extractClassName(get_class()));
            self::$_foreignKey = "{$foreignKey}_id";
        }
        return self::$_foreignKey;
    }

    private function _saveExternalFields() {
        foreach ($this->_externalFieldsChanged as $tableName => $fieldsValues) {
            $sql = $this->_getInsertSql($tableName, array_keys($fieldsValues));
            /** @var Record|TExternalFields $this */
            $params = [":{$this->_getTableForeignKey($tableName)}" => $this->id];
            foreach ($fieldsValues as $placeholder => $value) {
                $params[":{$placeholder}"] = $value;
            }
            foreach ($fieldsValues as $placeholder => $value) {
                $params[":_{$placeholder}"] = $value;
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
            return "`{$colName}`=:_{$colName}";
        }, $colNames));
        $foreignKey = $this->_getTableForeignKey($tableName);
        $sql = "INSERT INTO `{$tableName}` (`{$foreignKey}`,{$keys}) VALUES (:{$foreignKey},{$placeholders}) ";
        $sql.= "ON DUPLICATE KEY UPDATE {$onDuplicate};";
        return $sql;
    }

    private function _getFieldTableName($fieldName) {
        return self::$_fieldTable[$fieldName];
    }

    private function _getTableForeignKey($tableName) {
        return self::$_tableForeignKey[$tableName];
    }

    private function _deleteExternalFields($id) {
        foreach (self::$_tableForeignKey as $tableName => $foreignKey) {
            $sql = "DELETE FROM `{$tableName}` WHERE `{$foreignKey}`=? LIMIT 1";
            Record::getAdapter()->query($sql, [$id]);
        }
    }
}

function _createGetter($fieldName) {
    return function() use ($fieldName) {
        /** @var Record|TExternalFields $this */
        if (!isset($this->_externalFieldsCache[$fieldName])) {
            $tableName = self::$_fieldTable[$fieldName];
            $foreignKey = self::$_tableForeignKey[$tableName];
            $sql = "SELECT `{$fieldName}` FROM `{$tableName}` WHERE `{$foreignKey}`=?";
            $result = Record::getAdapter()->fetchSingleValue($sql, [$this->id]);
            $this->_externalFieldsCache[$fieldName] = ($result === false) ? null : $result;
        }
        return $this->_externalFieldsCache[$fieldName];
    };
}

function _createSetter($fieldName) {
    return function($value) use ($fieldName) {
        /** @var Record|TExternalFields $this */
        $this->_externalFieldsCache[$fieldName] = $value;
        $tableName = $this->_getFieldTableName($fieldName);
        $this->_externalFieldsChanged[$tableName][$fieldName] = $value;
        return $value;
    };
}
