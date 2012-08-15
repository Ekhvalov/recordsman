<?php
namespace RecordsMan;

trait TExternalFields {

    private static $_extFieldsDefinition = [];
    private static $_extFieldsDefsLoaded = false;

    private $_extFieldsLoaded  = [];
    private $_extFieldsChanged = [];

    public function set($fieldNameOrFieldsArray, $value = null) {
        if ( is_array($fieldNameOrFieldsArray) || $this->hasOwnField($fieldNameOrFieldsArray) || (strpos($fieldNameOrFieldsArray, "external.") === 0) ) {
            return parent::set($fieldNameOrFieldsArray, $value);
        }
        $fieldName = $fieldNameOrFieldsArray;
        $def = $this->_getExtFieldDef($fieldName);
        if ($def) {
            if (!$this->_isExtValuesLoaded($def['tabName'])) {
                $this->_loadExtFieldsValues($fieldName);
            }
            if (!in_array($def['tabName'], $this->_extFieldsChanged)) {
                $this->_extFieldsChanged[] = $def['tabName'];
            }
            return parent::set("external.{$fieldName}", $value);
        }
        return parent::set($fieldName, $value);
    }

    public function get($fieldName) {
        try {
            return parent::get($fieldName);
        } catch (\RecordsMan\RecordsManException $e) {
            $def = $this->_getExtFieldDef($fieldName);
            if ($def) {
                if (!$this->_isExtValuesLoaded($def['tabName'])) {
                    $this->_loadExtFieldsValues($fieldName);
                }
                return parent::get("external.{$fieldName}");
            }
        }
        $context = get_class($this);
        throw new RecordsManException("Field {$fieldName} are not exists in class {$context}", 40);
    }

    public function save($testRelations = true) {
        parent::save($testRelations);
        $adapter = self::getAdapter();
        foreach ($this->_getChangedDefs() as $def) {
            $sql = Helper::createSelectQuery($def['tabName'], "{$def['foreignKey']}={$this->get('id')}", null, 1);
            $row = $adapter->fetchRow($sql);
            if (!empty($row)) {
                // UPDATING
                $sql = "UPDATE `{$def['tabName']}` SET ";
                $params = [];
                foreach($def['fields'] as $field) {
                    $sql.= "`{$field}`=?,";
                    $params[] = $this->get($field);
                }
                $sql = rtrim($sql, ',') . " WHERE `{$def['foreignKey']}`={$this->get('id')} LIMIT 1";
                $adapter->query($sql, $params);
            } else {
                // INSERTING
                $values = [$def['foreignKey'] => $this->get('id')];
                foreach($def['fields'] as $field) {
                    $values[$field] = $this->get($field);
                }
                $adapter->insert($def['tabName'], $values);
            }
        }
        return $this;
    }

    public function drop() {
        if (!self::$_extFieldsDefsLoaded) {
            $this->_loadExtFieldsDefs();
        }
        foreach(self::$_extFieldsDefinition as $def) {
            $this->dropExternalRecord($def['tabName']);
        }
        parent::drop();
        return $this;
    }

    public function dropExternalRecord($tabName) {
        if (!self::$_extFieldsDefsLoaded) {
            $this->_loadExtFieldsDefs();
        }
        $def = self::$_extFieldsDefinition[$tabName];
        if (!empty($def)) {
            $sql = "DELETE FROM `{$def['tabName']}` WHERE `{$def['foreignKey']}`=? LIMIT 1";
            return self::getAdapter()->query($sql, [$this->get('id')]);
        }
        return 0;
    }

    private function _loadExtFieldsValues($fieldName) {
        $def = $this->_getExtFieldDef($fieldName);
        $sql = Helper::createSelectQuery($def['tabName'], "{$def['foreignKey']}={$this->get('id')}", null, 1);
        $row = self::getAdapter()->fetchRow($sql);
        $fields = [];
        foreach($def['fields'] as $fieldName) {
            $fields["external.{$fieldName}"] = empty($row) ? '' : $row[$fieldName];
        }
        $this->set($fields);
        if (!$this->_isExtValuesLoaded($def['tabName'])) {
            $this->_extFieldsLoaded[] = $def['tabName'];
        }
        return $fields;
    }

    private function _getExtFieldDef($fieldName) {
        if (!self::$_extFieldsDefsLoaded) {
            $this->_loadExtFieldsDefs();
        }
        foreach(self::$_extFieldsDefinition as $def) {
            if (in_array($fieldName, $def['fields'])) {
                return $def;
            }
        }
        return null;
    }

    private function _isExtValuesLoaded($tabName) {
        return in_array($tabName, $this->_extFieldsLoaded);
    }

    private function _getChangedDefs() {
        if (!self::$_extFieldsDefsLoaded) {
            $this->_loadExtFieldsDefs();
        }
        $defs = [];
        foreach($this->_extFieldsChanged as $tabName) {
            $defs[] = self::$_extFieldsDefinition[$tabName];
        }
        return $defs;
    }

    private function _loadExtFieldsDefs() {
        if (!isset(self::$externalFields)) {
            self::$_extFieldsDefsLoaded = true;
            return ;
        }
        foreach(self::$externalFields as $tabName => $foreignKey) {
            $def = [
                'tabName'    => $tabName,
                'foreignKey' => $foreignKey,
                'fields'     => []
            ];
            foreach(self::getAdapter()->getTableColumns($tabName) as $columnDef) {
                if ( $this->hasOwnField($columnDef['Field']) || ($columnDef['Field'] == $foreignKey)) {
                    continue ;
                }
                $def['fields'][] = $columnDef['Field'];
            }
            self::$_extFieldsDefinition[$tabName] = $def;
        }
        self::$_extFieldsDefsLoaded = true;
    }


}

?>
