<?php
namespace RecordsMan;

trait TExternalFields
{
    protected static $_fieldTable = [];
    protected static $_tableForeignKeys = [];
    private static $_initialized = false;
    private static $_foreignKey;

    protected $_externalFieldsCache = [];
    protected $_externalFieldsChanged = [];
    protected $deletedId;

    /**
     * @param string $fieldName Column name
     * @param string $tableName Table name
     * @param null|string|array $foreignKey
     * if $foreignKey is null then $foreignKey = {class_name}_id
     * if $foreignKey is array then it should be like
     * ['key1' => callable(Record): key1_value, 'key2' => callable(Record): key2_value]
     * @throws \RuntimeException
     */
    public static function addExternalField($fieldName, $tableName, $foreignKey = null) {
        if (!self::$_initialized) {
            self::_externalFieldsInit();
            self::$_initialized = true;
        }
        self::$_fieldTable[$fieldName] = $tableName;
        self::_assignForeignKeyToTable($foreignKey, $tableName);
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
        self::addTrigger(self::DELETED, function(self $record, $_, $deletedId) {
            $record->deletedId = $deletedId;
            $record->_deleteExternalFields();
        });
    }

    /**
     * @param null|string|array $foreignKey
     * @param string $tableName
     * @throws \RuntimeException
     */
    private static function _assignForeignKeyToTable($foreignKey, $tableName) {
        if (is_null($foreignKey)) {
            $foreignKey =  Helper::ucFirstToUnderscore(
                Helper::extractClassName(get_class())
            );
            self::$_tableForeignKeys[$tableName] = [
                "{$foreignKey}_id" => self::_getRecordIdCallback()
            ];
            return;
        }
        if (is_string($foreignKey)) {
            self::$_tableForeignKeys[$tableName] = [
                $foreignKey => self::_getRecordIdCallback()
            ];
            return;
        }
        if (is_array($foreignKey) && !empty($foreignKey)) {
            foreach ($foreignKey as $keyName => $getKeyValue) {
                $getKeyValue = $getKeyValue === true ? self::_getRecordIdCallback() : $getKeyValue;
                if (!is_callable($getKeyValue)) {
                    throw new \RuntimeException("Invalid foreignKey {$keyName} should be callable or 'true'.");
                }
                self::$_tableForeignKeys[$tableName][$keyName] = $getKeyValue;
            }
            return;
        }
        throw new \RuntimeException("Invalid foreignKey type.");
    }

    protected function _saveExternalFields() {
        foreach ($this->_externalFieldsChanged as $tableName => $fieldsValues) {
            $sql = $this->_getInsertSql($tableName, array_keys($fieldsValues));
            /** @var Record|TExternalFields $this */
            $params = [];
            foreach (self::$_tableForeignKeys[$tableName] as $keyName => $getKeyValue) {
                $params[":{$keyName}"] = $getKeyValue($this);
            }
            foreach ($fieldsValues as $placeholder => $value) {
                $params[":{$placeholder}"] = $value;
                $params[":_{$placeholder}"] = $value; // for "ON DUPLICATE"
            }
            Record::getAdapter()->query($sql, $params);
            unset($this->_externalFieldsChanged[$tableName]);
        }
    }

    private function _getInsertSql($tableName, $colNames) {
        $columns = implode(',', array_map(function($colName) {
            return "`{$colName}`";
        }, $colNames));
        $placeholders = implode(',', array_map(function($placeholder) {
            return ":{$placeholder}";
        }, $colNames));
        $onDuplicate = implode(',', array_map(function($colName) {
            return "`{$colName}`=:_{$colName}";
        }, $colNames));
        $foreignKeys = implode(',', array_map(function ($keyName) {
            return "`{$keyName}`";
        }, array_keys($this->_getTableForeignKeys($tableName))));
        $fkPlaceholders = implode(',', array_map(function ($keyName) {
            return ":{$keyName}";
        }, array_keys($this->_getTableForeignKeys($tableName))));
        $sql = "INSERT INTO `{$tableName}` ({$foreignKeys},{$columns}) ";
        $sql.= "VALUES ({$fkPlaceholders},{$placeholders}) ";
        $sql.= "ON DUPLICATE KEY UPDATE {$onDuplicate};";
        return $sql;
    }

    /**
     * @param string $tableName
     * @return string
     */
    protected function _getForeignKeySql($tableName) {
        $sql = '';
        foreach (self::$_tableForeignKeys[$tableName] as $keyName => $_) {
            $sql.= " `{$keyName}`=? AND";
        }
        return rtrim($sql, 'AND');
    }

    protected function _getFieldTableName($fieldName) {
        return self::$_fieldTable[$fieldName];
    }

    /**
     * @param string $tableName
     * @return array
     */
    private function _getTableForeignKeys($tableName) {
        return self::$_tableForeignKeys[$tableName];
    }

    protected function _deleteExternalFields() {
        foreach (self::$_tableForeignKeys as $tableName => $foreignKey) {
            $sql = "DELETE FROM `{$tableName}` ";
            $sql.= "WHERE {$this->_getForeignKeySql($tableName)}  LIMIT 1";
            Record::getAdapter()->query($sql, $this->_getForeignKeyValue($tableName));
        }
    }

    /**
     * @return callable
     */
    private static function _getRecordIdCallback() {
        /**
         * @param TExternalFields|Record $record
         * @return int
         */
        return function (self $record) {
            return $record->id ? $record->id : $record->deletedId;
        };
    }

    /**
     * @param string $tableName
     * @return array
     */
    protected function _getForeignKeyValue($tableName) {
        $values = [];
        foreach (self::$_tableForeignKeys[$tableName] as $getKeyValue) {
            $values[] = $getKeyValue($this);
        }
        return $values;
    }
}

function _createGetter($fieldName) {
    return function() use ($fieldName) {
        /** @var Record|TExternalFields $this */
        if (!isset($this->_externalFieldsCache[$fieldName])) {
            $tableName = $this->_getFieldTableName($fieldName);
            $sql = "SELECT `{$fieldName}` FROM `{$tableName}` ";
            $sql.= "WHERE {$this->_getForeignKeySql($tableName)}";
            $result = $this->getAdapter()
                ->fetchSingleValue($sql, $this->_getForeignKeyValue($tableName));
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
