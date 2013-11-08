<?php
namespace RecordsMan;

abstract class Record {

    const RELATION_NONE = false;
    const RELATION_BELONGS = 1;
    const RELATION_MANY = 2;

    private static $_loader = null;

    private $_fields = [];
    private $_foreign = [];
    private $_changed = false;


    ////////// Records loading static methods

    /**
     * Loads a single instance by primary key
     *
     * @param int $id PK value
     * @return Record Returns Record object or throws exception if no records found
     * @throws RecordsManException
     */
    public static function load($id) {
        $record = static::findFirst("id={$id}");
        if (empty($record)) {
            throw new RecordsManException("Can't load " . get_called_class() . " with id '{$id}'", 1);
        }
        return $record;
    }

    /**
     * Creates an instance
     *
     * @param array $fields Initialize with fields values
     * @return Record
     */
    public static function create($fields = []) {
        $record = new static(self::getLoader()->getFieldsDefinition(get_called_class()));
        if (!empty($fields) && is_array($fields)) {
            $record->set($fields);
        }
        return $record;
    }

    /**
     * Finds records by passed conditions, and returns RecordSet
     *
     * If called without parameters, returns all record in RecordSet
     *
     * @param array|string|Condition $condition Instance of Condition or something that can be reduced to Condition (see Condition class description)
     * @param array|string $order String, containing field name to ascending ordering, or array like ['title' => 'ASC', 'price' => 'DESC']
     * @param array|int $limit Integer value for limiting, or array like [10, 20]
     * @return RecordSet
     */
    public static function find($condition = null, $order = null, $limit = null) {
        $qualifiedName = Helper::qualifyClassName(get_called_class());
        return RecordSet::create($qualifiedName, $condition, $order, $limit);
    }

    /**
     * Finds & returns all records in table. Shortcut for find(null, $order)
     *
     * @param array|string $order String, containing field name to ascending ordering, or array like ['title' => 'ASC', 'price' => 'DESC']
     * @return RecordSet
     */
    public static function all($order = null) {
        return static::find(null, $order);
    }

    /**
     * Finds first record by given conditions.
     *
     * @param array|string|Condition $condition See find() descr.
     * @param array|string $order See find() descr.
     * @return null|Record Returns Record object or null if no records found
     */
    public static function findFirst($condition = null, $order = null) {
        $rows = static::_select($condition, $order, 1);
        return empty($rows) ? null : static::_fromArray($rows[0]);
    }

    /**
     * Returns random set of records (optionally filtered by condition).
     *
     * @param null|array|Condition $condition
     * @param null|array|int $limit
     * @return RecordSet
     */
    public static function findRandom($condition = null, $limit = null) {
        $qualifiedName = Helper::qualifyClassName(get_called_class());
        $sql = Helper::createRandomSelectQuery(
            self::getLoader()->getClassTableName($qualifiedName),
            $condition,
            $limit
        );
        return RecordSet::createFromSql($qualifiedName, $sql);
    }

    /**
     * Finds & returns records by custom SQL query
     *
     * @param string $sqlQuery Query text (input parameters can be placeholdered by "?")
     * @param array $sqlParams Parameters array for substitution in query
     * @param bool $singleItem If true, returns single Record object, else - RecordSet
     * @return null|Record|RecordSet
     */
    public static function findBySql($sqlQuery, $sqlParams = null, $singleItem = false) {
        if ($singleItem) {
            $result = self::_dbResult($sqlQuery, $sqlParams);
            if (!empty($result)) {
                return new static($result[0]);
            }
            return null;
        }
        $qualifiedName = Helper::qualifyClassName(get_called_class());
        return RecordSet::createFromSql($qualifiedName, $sqlQuery, $sqlParams);
    }

    public static function loadFromCache($id, $autocache = false, $lifetime = null) {
        $qualifiedName = Helper::qualifyClassName(get_called_class());
        $cacher = self::getCacheProvider();
        $itemFields = $cacher->getRecord($qualifiedName, $id);
        if (is_null($itemFields)) {
            $item = static::load($id);
            if ($autocache) {
                $item->cache($lifetime);
            }
        } else {
            $item = static::_fromArray($itemFields);
        }
        return $item;
    }

    public static function loadCachedSet($key) {
        $qualifiedName = Helper::qualifyClassName(get_called_class());
        return RecordSet::createFromCache($qualifiedName, $key);
    }

    /**
     * For inner use
     *
     * @param array|string|Condition $condition
     * @param string|array $order
     * @param int|array $limit
     * @return array
     */
    public static function _select($condition = null, $order = null, $limit = null) {
        $sql = Helper::createSelectQuery(
            self::getLoader()->getClassTableName(get_called_class()),
            $condition,
            $order,
            $limit
        );
        $rows = self::_dbResult($sql);
        return empty($rows) ? [] : $rows;
    }

    //TODO: tests
    public static function count($condition = null) {
        $sql = Helper::createCountQuery(
            self::getLoader()->getClassTableName(get_called_class()),
            $condition
        );
        return intval(self::getAdapter()->fetchSingleValue($sql));
    }


    ////////// Triggers

    public static function addTrigger($triggerName, \Closure $callback) {
        self::getLoader()->addClassTrigger(get_called_class(), $triggerName, $callback);
    }

    public function callTrigger($triggerName, $argsArray = []) {
        $context = $this->_getContext();
        array_unshift($argsArray, $this);
        $result = null;
        foreach(self::getLoader()->getClassTriggersCallbacks($context, $triggerName) as $callback) {
            $result = call_user_func_array($callback, $argsArray);
            if ($result === false) {
                break;
            }
        }
        return $result;
    }


    ////////// Fields manipulating methods

    public function get($fieldName) {
        $context = $this->_getContext();
        if (array_key_exists($fieldName, $this->_fields)) {
            return $this->_fields[$fieldName];
        }
        $foreignClass = Helper::getClassNamespace($context) . ucfirst(Helper::getSingular($fieldName));
        if (class_exists($foreignClass)) {
            $relation = $this->getRelationTypeWith($foreignClass);
            if ($relation != self::RELATION_NONE) {
                return $this->loadForeign($foreignClass);
            }
        }
        throw new RecordsManException("Field {$fieldName} are not exists in class {$context}", 40);
    }

    public function set($fieldNameOrFieldsArray, $value = null) {
        if (is_array($fieldNameOrFieldsArray)) {
            foreach($fieldNameOrFieldsArray as $fieldName => $fieldValue) {
                $this->set($fieldName, $fieldValue);
            }
            return $this;
        }
        $field = $fieldNameOrFieldsArray;
        if ($field == 'id') {
            throw new RecordsManException("Can't change `id` field", 70);
        }
        $context = $this->_getContext();
        if (
            self::getLoader()->isFieldProtected($context, $field) &&
            !$this->_isInnerCall(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
        ) {
            throw new RecordsManException("Can't set value of protected field {$context}::{$field}", 71);
        }
        if ($this->hasOwnField($field)) {
            $this->_fields[$field] = $value;
            $this->_changed = true;
            return $this;
        }
        $foreignClass = Helper::getClassNamespace($context) . ucfirst(Helper::getSingular($fieldNameOrFieldsArray));
        if (class_exists($foreignClass)) {
            $relation = $this->getRelationTypeWith($foreignClass);
            if ($relation != self::RELATION_NONE) {
                return $this->setForeign($foreignClass, $value);
            }
        }
        $this->_fields[$fieldNameOrFieldsArray] = $value;
        return $this;
    }

    public function hasOwnField($fieldName) {
        return self::getLoader()->isFieldExists($this->_getContext(), $fieldName);
    }

    //TODO: tests
    public function toArray($neededFields = []) {
        $context = $this->_getContext();
        $classFields = self::getLoader()->getFieldsDefinition($context);
        $actualFields = $this->_fields;
        // filtering only own fields
        foreach($classFields as $fieldName => $_) {
            $actualFields[$fieldName] = $this->get($fieldName);
        }
        if (empty($neededFields)) {
            return $actualFields;
        }
        $res = [];
        foreach($neededFields as $fieldName) {
            $res[$fieldName] = (array_key_exists($fieldName, $actualFields)) ? $actualFields[$fieldName] : null;
        }
        return $res;
    }

    public function isMatch($condition) {
        return Condition::create($condition)->test($this->_fields);
    }


    ////////// Creating / deleting/ updating

    public function reload() {
        if (!$this->get('id')) {
            return $this;
        }
        $sql = Helper::createSelectQuery(
            self::getLoader()->getClassTableName($this->_getContext()),
            "id = {$this->get('id')}",
            null,
            1
        );
        $rows = self::_dbResult($sql);
        $this->_fields = $rows[0];
        $this->_foreign = [];
        return $this;
    }

    public function save($testRelations = true) {
        $triggerResult = $this->callTrigger('save');
        if ($triggerResult === false) {
            return $this;
        }
        $thisId = $this->get('id');
        $context = $this->_getContext();
        $tableName = self::getLoader()->getClassTableName($context);
        $classFields = self::getLoader()->getFieldsDefinition($context);
        $actualFields = [];
        // filtering only own fields
        foreach($classFields as $fieldName => $_) {
            $value = $this->get($fieldName);
            if ( ($fieldName != 'id') && $this->hasOwnField($fieldName) ) {
                $actualFields[$fieldName] = $value;
            }
        }
        if ($testRelations) {
            $this->_checkForeignKeys();
        }
        if ($thisId) {
            // updating existing entry
            if ($this->hasOwnField('updated_at')) {
                $this->_fields['updated_at'] = $actualFields['updated_at'] = time();
            }
            $sqlParams = [];
            $sql = "UPDATE `{$tableName}` SET ";
            foreach($actualFields as $fieldName => $value) {
                $sql.= "`{$fieldName}`=?,";
                $sqlParams[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql.= " WHERE `id`={$thisId} LIMIT 1";
            self::getAdapter()->query($sql, $sqlParams);
            return $this;
        }
        // creating new entry
        if ($this->hasOwnField('created_at')) {
            $this->_fields['created_at'] = $actualFields['created_at'] = time();
        }
        self::getAdapter()->insert($tableName, $actualFields);
        $this->_fields['id'] = self::getAdapter()->getLastInsertId();
        $this->_updateRelatedCounters();
        return $this;
    }

    public function drop() {
        $triggerResult = $this->callTrigger('drop');
        if ($triggerResult === false) {
            return $this;
        }
        $thisId = $this->get('id');
        if (!$thisId) {
            return $this;
        }
        $context = $this->_getContext();
        $this->_deleteRelatedRecords();
        $tableName = self::getLoader()->getClassTableName($context);
        $sql = "DELETE FROM `{$tableName}` WHERE `id`={$thisId} LIMIT 1";
        self::getAdapter()->query($sql);
        $this->_updateRelatedCounters();
        $this->_fields['id'] = 0;
        return $this;
    }

    public function cache($lifetime = null) {
        $cacher = self::getCacheProvider();
        $cacher->storeRecord($this, $lifetime);
        return $this;
    }


    ////////// Class relations manipulation

    public function getRelationTypeWith($class) {
        if ($class instanceof Record) {
            $class = Helper::qualifyClassName(get_class($class));
        }
        return self::getLoader()->getClassRelationTypeWith($this->_getContext(), $class);
    }

    public function getRelationParamsWith($class) {
        return self::getLoader()->getClassRelationParamsWith($this->_getContext(), $class);
    }

    public function loadForeign($className) {
        $foreignClass = Helper::qualifyClassName($className);
        if (isset($this->_foreign[$foreignClass])) {
            return $this->_foreign[$foreignClass];
        }
        $relationType = $this->getRelationTypeWith($foreignClass);
        $relationParams = $this->getRelationParamsWith($foreignClass);
        switch ($relationType) {
            case self::RELATION_BELONGS:
                $fKeyValue = $this->get($relationParams['foreignKey']);
                $this->_foreign[$foreignClass] = $fKeyValue ? $foreignClass::load($fKeyValue) : null;
                break;
            case self::RELATION_MANY:
                $this->_foreign[$foreignClass] = RecordSet::createFromForeign($this, $foreignClass);
                break;
        }
        return $this->_foreign[$foreignClass];
    }

    public function setForeign($className, $records) {
        $context = $this->_getContext();
        $foreignClass = Helper::qualifyClassName($className);
        $relationType = $this->getRelationTypeWith($foreignClass);
        $relationParams = $this->getRelationParamsWith($foreignClass);
        switch ($relationType) {
            case self::RELATION_BELONGS:
                if (!($records instanceof Record)) {
                    throw new RecordsManException("Can't set foreign from " . gettype($records));
                }
                if (Helper::qualifyClassName(get_class($records)) != $foreignClass) {
                    throw new RecordsManException("Can't set foreign ({$foreignClass}) from " . get_class($records));
                }
                if (!$records->id) {
                    $records->save();
                }
                $this->_foreign[$foreignClass] = $records;
                $field = $relationParams['foreignKey'];
                $this->$field = $records->id;
                break;
            case self::RELATION_MANY:
                foreach($records as $record) {
                    $record->setForeign($context, $this);
                }
                break;
            default:
                throw new RecordsManException("Class {$context} hasn't relation with {$foreignClass}", 10);
        }
        return $this;
    }


    ////////// Magic

    public function __get($fieldName) {
        return $this->get($fieldName);
    }

    public function __set($fieldName, $value) {
        $this->set($fieldName, $value);
    }


    ////////// Loader related methods

    public static function setLoader(Loader $loader) {
        self::$_loader = $loader;
    }

    /**
     * @static
     * @return Loader
     */
    public static function getLoader() {
        return self::$_loader;
    }

    /**
     *
     * @return IDBAdapter
     */
    public static function getAdapter() {
        return self::$_loader->getAdapter();
    }

    /**
     * @return IRecordsCacher
     */
    public static function getCacheProvider() {
        return self::$_loader->getCacheProvider();
    }


    ////////// Class meta manipulation methods

    final public static function getMetaData() {
        $called = get_called_class();
        $tableName = isset(static::$tableName) ? static::$tableName : Helper::extractTableNameFromClassName($called);
        $hasMany = [];
        $belongsTo = [];
        if (isset(static::$hasMany) && is_array(static::$hasMany)) {
            foreach(static::$hasMany as $class => $relationParams) {
                $hasMany[$class] = is_array($relationParams) ? $relationParams : ['foreignKey' => $relationParams];
            }
        }
        if (isset(static::$belongsTo) && is_array(static::$belongsTo)) {
            foreach(static::$belongsTo as $class => $relationParams) {
                $belongsTo[$class] = is_array($relationParams) ? $relationParams : ['foreignKey' => $relationParams];
            }
        }
        return [
            'tableName' => $tableName,
            'hasMany'   => $hasMany,
            'belongsTo' => $belongsTo,
            'protected' => (isset(static::$protected) && is_array(static::$protected)) ? static::$protected : []
        ];
    }

    public function getQualifiedClassname() {
        return $this->_getContext();
    }


    ////////// Closed methods

    protected function __construct($initValues) {
        $this->_fields = $initValues;
    }

    public static function _fromArray($fieldsArray) {
        return new static($fieldsArray);
    }

    private static function _dbResult($sqlQuery, $sqlParams = null) {
        return self::getAdapter()->fetchRows($sqlQuery, $sqlParams);
    }

    protected function _getContext() {
        return Helper::qualifyClassName(get_class($this));
    }

    private function _isInnerCall($trace) {
        $depth = count($trace);
        if ($depth < 2) {
            return false;
        }
        for($i=1; $i<$depth; $i++) {
            if (!isset($trace[$i]['class'])) {
                return false;
            }
            if (($trace[$i]['class'] == __CLASS__) && (($trace[$i]['function'] == '__set') || ($trace[$i]['function'] == 'set') || ($trace[$i]['function'] == 'create'))) {
                continue;
            }
            return (($trace[$i]['class'] == __CLASS__) || ($trace[$i]['class'] == get_class($this)));
        }
        return false;
    }

    private function _deleteRelatedRecords() {
        $context = $this->_getContext();
        $loader = self::getLoader();
        $relatedClasses = $loader->getClassRelations($context, Record::RELATION_MANY);
        if (!empty($relatedClasses)) {
            foreach($relatedClasses as $relClass) {
                //TODO: check, if included traits, redeclared drop() method or extra params defined, needs object-by-obect deleting
                /*
                $foreignRelations = $loader->getClassRelations($relClass, Record::RELATION_MANY);
                $classCounters = $loader->getClassCounters($relClass, $context);
                if (empty($foreignRelations) && empty($classCounters)) {
                    // group deleting if foreign class has no relations & counters
                    $foreignKey = $this->getRelationParamsWith($relClass)['foreignKey'];
                    $sql = "DELETE FROM `" . $loader->getClassTableName($relClass) . "` ";
                    $sql.= "WHERE `{$foreignKey}`={$this->get('id')}";
                    self::getAdapter()->query($sql);
                    continue ;
                }
                */
                $relationParams = $loader->getClassRelationParamsWith($context, $relClass);
                if (isset($relationParams['through'])) {
                    continue;
                }
                foreach($this->loadForeign($relClass) as $foreign) {
                    $foreign->drop();
                }
            }
        }
    }

    private function _updateRelatedCounters() {
        $context = $this->_getContext();
        $loader = self::getLoader();
        $classesWithCounters = $loader->getClassCounters($context);
        $thisTab = $loader->getClassTableName($context);
        foreach($classesWithCounters as $className => $counterField) {
            $relationParams = $loader->getClassRelationParamsWith($context, $className);
            $foreignKey = $relationParams['foreignKey'];
            $foreignItemId = $this->get($foreignKey);
            if ($context == $className) {
                // Self-related classes
                if (!$foreignItemId) {
                    continue ;
                }
                $parentItem = $className::load($foreignItemId);
                $sql = "SELECT COUNT(*) FROM `{$thisTab}` WHERE `{$foreignKey}`={$foreignItemId}";
                $count = self::getAdapter()->fetchSingleValue($sql);
                $parentItem->set($counterField, $count)->save(false);
                continue ;
            }
            // Foreign relations
            $parentItem = $className::load($foreignItemId);
            $parentTab = $loader->getClassTableName($className);
            $sql = "UPDATE `{$parentTab}` SET `{$counterField}` = (";
            $sql.= "SELECT COUNT(*) FROM `{$thisTab}` WHERE `{$foreignKey}`={$parentItem->id}";
            $sql.= ") WHERE `id`={$parentItem->id} LIMIT 1";
            self::getAdapter()->query($sql);
        }
    }

    public function _checkForeignKeys() {
        $context = $this->_getContext();
        $loader = $this->getLoader();
        $relatedClasses = $loader->getClassRelations($context, Record::RELATION_BELONGS);
        foreach($relatedClasses as $className) {
            if ($context == $className) {
                // Skipping self-related classes
                continue ;
            }
            $relationParams = $this->getRelationParamsWith($className);
            $foreignItemId = $this->get($relationParams['foreignKey']);
            try {
                $className::load($foreignItemId);
            } catch(\Exception $e) {
                throw new RecordsManException("Related item of class {$className} (#{$foreignItemId}) are not exists", 85);
            }
        }
    }


}
