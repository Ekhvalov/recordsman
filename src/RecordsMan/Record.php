<?php
namespace RecordsMan;

/**
 * @property-read int $id
 */
abstract class Record
{
    const RELATION_NONE    = false;
    const RELATION_BELONGS = 1;
    const RELATION_MANY    = 2;

    const SAVE         = 1;
    const SAVE_UPDATE  = 2;
    const SAVE_CREATE  = 4;
    const SAVED        = 8;
    const SAVE_UPDATED = 16;
    const SAVE_CREATED = 32;
    const DELETE       = 64;
    const DELETED      = 128;
    //Note: don't forget to add trigger def to returned array of self::getDefaultTriggersList() when adding new

    /** @var Loader $_loader */
    private static $_loader = null;

    private $_fields = [];
    private $_foreign = [];
    private $_changed = [];
    private $_context;

    protected static $hasMany;
    protected static $belongsTo;
    protected static $tableName;


    ////////// Records loading static methods

    /**
     * Loads a single instance by primary key
     *
     * @param int $id PK value
     * @return static Returns Record object or throws exception if no records found
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
     * @return static
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
     * @param array|string|Condition $condition Instance of Condition or something that can be reduced to Condition
     * (see Condition class description)
     * @param array|string $order String, containing field name to ascending ordering,
     * or array like ['title' => 'ASC', 'price' => 'DESC']
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
     * @param array|string $order String, containing field name to ascending ordering,
     * or array like ['title' => 'ASC', 'price' => 'DESC']
     * @return RecordSet
     */
    public static function all($order = null) {
        return static::find(null, $order);
    }

    /**
     * Finds first record by given conditions.
     *
     * @param array|string|Condition $condition
     * @see Record::find() description
     * @param array|string $order
     * @see Record::find() description
     * @return null|static Returns Record object or null if no records found
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
     * @return null|static|RecordSet
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

    /**
     * @param int $id
     * @param bool $autocache
     * @param null $lifetime
     * @return static
     * @throws RecordsManException
     */
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

    /**
     * @param $key
     * @return null|RecordSet
     */
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
    /**
     * @param $condition
     * @return int
     */
    public static function count($condition = null) {
        $sql = Helper::createCountQuery(
            self::getLoader()->getClassTableName(get_called_class()),
            $condition
        );
        return intval(self::getAdapter()->fetchSingleValue($sql));
    }


    ////////// Triggers
    /**
     * @param $triggerType
     * @param callable $callback
     */
    public static function addTrigger($triggerType, \Closure $callback) {
        $defTriggers = self::getDefaultTriggersList();
        $loader = self::getLoader();
        $calledClass = get_called_class();
        if (is_int($triggerType) && !in_array($triggerType, $defTriggers)) {
            // Interpret as a combination of default triggers
            foreach ($defTriggers as $defTrigger) {
                ($triggerType & $defTrigger) && $loader->addClassTrigger($calledClass, $defTrigger, $callback);
            }
            return;
        }
        $loader->addClassTrigger($calledClass, $triggerType, $callback);
    }

    /**
     * @param string $name
     * @param null|\Closure $getter Non-static scope closure
     * @param null|\Closure $setter Non-static scope closure
     */
    public static function addProperty($name, $getter = null, $setter = null) {
        self::getLoader()->addClassProperty(get_called_class(), $name, $getter, $setter);
    }

    public function callTrigger($triggerName, $argsArray = []) {
        $context = $this->_getContext();
        array_unshift($argsArray, $triggerName);
        array_unshift($argsArray, $this);
        $result = null;
        foreach (self::getLoader()->getClassTriggersCallbacks($context, $triggerName) as $callback) {
            $result = call_user_func_array($callback, $argsArray);
            if ($result === false) {
                break;
            }
        }
        return $result;
    }


    ////////// Fields manipulating methods
    /**
     * @param string $fieldName
     * @return mixed|null
     */
    public function get($fieldName) {
        $context = $this->_getContext();
        if (self::getLoader()->hasClassPropertyGetterCallbacks($context, $fieldName)) {
            $result = $this->getRawFieldValue($fieldName);
            foreach ($this->_getGetterCallbacks($fieldName) as $callback) {
                $result = call_user_func($callback->bindTo($this, $this), $result);
            }
            return $result;
        }
        if (isset($this->_changed[$fieldName])) {
            return $this->_changed[$fieldName];
        }
        if (isset($this->_fields[$fieldName])) {
            return $this->_fields[$fieldName];
        }
        $foreignClass = $this->_lookupForeignByFieldname($context, $fieldName);
        if (class_exists($foreignClass)) {
            $relation = $this->getRelationTypeWith($foreignClass);
            if ($relation != self::RELATION_NONE) {
                return $this->loadForeign($foreignClass);
            }
        }
        return null;
    }

    /**
     * @param string $fieldName
     * @return null
     */
    public function getRawFieldValue($fieldName) {
        return (isset($this->_fields[$fieldName])) ? $this->_fields[$fieldName] : null;
    }

    protected function _getGetterCallbacks($fieldName) {
        return self::getLoader()->getClassPropertyGetterCallbacks($this->_getContext(), $fieldName);
    }

    protected function _getSetterCallbacks($fieldName) {
        return self::getLoader()->getClassPropertySetterCallbacks($this->_getContext(), $fieldName);
    }

    /**
     * @param string $fieldNameOrFieldsArray
     * @param null|mixed $value
     * @return Record
     * @throws RecordsManException
     */
    protected function set($fieldNameOrFieldsArray, $value = null) {
        if (is_array($fieldNameOrFieldsArray)) {
            foreach ($fieldNameOrFieldsArray as $fieldName => $fieldValue) {
                $this->set($fieldName, $fieldValue);
            }
            return $this;
        }
        $fieldName = $fieldNameOrFieldsArray;
        if ($fieldName == 'id') {
            throw new RecordsManException("Can't change `id` field", 70);
        }
        $context = $this->_getContext();
        if (self::getLoader()->hasClassPropertySetterCallbacks($context, $fieldName)) {
            foreach ($this->_getSetterCallbacks($fieldName) as $callback) {
                $value = call_user_func($callback->bindTo($this, $this), $value);
            }
        }
        if ($this->hasOwnField($fieldName)) {
            if ($this->_fields[$fieldName] != $value) {
                $this->_changed[$fieldName] = $value;
            } elseif (isset($this->_changed[$fieldName])) {
                unset($this->_changed[$fieldName]);
            }
            return $this;
        }
        $foreignClass = Helper::getClassNamespace($context) . ucfirst(Helper::getSingular($fieldNameOrFieldsArray));
        if (class_exists($foreignClass)) {
            $relation = $this->getRelationTypeWith($foreignClass);
            if ($relation != self::RELATION_NONE) {
                return $this->setForeign($foreignClass, $value);
            }
        }
        $this->_changed[$fieldNameOrFieldsArray] = $value;
        return $this;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function hasOwnField($fieldName) {
        return self::getLoader()->isFieldExists($this->_getContext(), $fieldName);
    }

    //TODO: tests
    /**
     * @param array $neededFields
     * @return array
     */
    public function toArray($neededFields = []) {
        $context = $this->_getContext();
        $classFields = self::getLoader()->getFieldsDefinition($context);
        $actualFields = $this->_fields;
        // filtering only own fields
        foreach ($classFields as $fieldName => $_) {
            $actualFields[$fieldName] = $this->get($fieldName);
        }
        if (empty($neededFields)) {
            return $actualFields;
        }
        $res = [];
        foreach ($neededFields as $fieldName) {
            $res[$fieldName] = (array_key_exists($fieldName, $actualFields)) ? $actualFields[$fieldName] : null;
        }
        return $res;
    }

    /**
     * @param $condition
     * @return bool
     */
    public function isMatch($condition) {
        return Condition::create($condition)->test($this->_fields);
    }


    ////////// Creating / deleting/ updating
    /**
     * @return bool
     */
    public function wasChanged() {
        return !$this->id || !empty($this->_changed);
    }

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
        $this->_changed = [];
        return $this;
    }

    /**
     * @param bool $testRelations
     * @return static
     */
    public function save($testRelations = true) {
        $changedKeys = array_keys($this->_changed);
        if ($this->callTrigger(self::SAVE, [$changedKeys]) === false) {
            return $this;
        }
        if (!$this->wasChanged()) {
            return $this;
        }
        $thisId = $this->get('id');
        $context = $this->_getContext();
        $tableName = self::getLoader()->getClassTableName($context);
        if ($testRelations) {
            $this->_checkForeignKeys();
        }
        if ($thisId) {
            // updating existing entry
            if ($this->hasOwnField('updated_at')) {
                $this->_changed['updated_at'] = time();
            }
            if ($this->callTrigger(self::SAVE_UPDATE, [$changedKeys]) === false) {
                return $this;
            }
            $sqlParams = [];
            $sql = "UPDATE `{$tableName}` SET ";
            foreach ($this->_getChangedOwnFields() as $fieldName => $value) {
                $sql.= "`{$fieldName}`=?,";
                $sqlParams[] = $value;
            }
            $sql = rtrim($sql, ',');
            $sql.= " WHERE `id`={$thisId} LIMIT 1";
            self::getAdapter()->query($sql, $sqlParams);
            $this->_updateRelatedCounters();
            $this->callTrigger(self::SAVED, [$changedKeys]);
            $this->callTrigger(self::SAVE_UPDATED, [$changedKeys]);
            return $this->_applyChanges();
        }
        // creating new entry
        if ($this->hasOwnField('created_at')) {
            $this->_changed['created_at'] = time();
        }
        if ($this->callTrigger(self::SAVE_CREATE, [$this->_changed]) === false) {
            return $this;
        }
        self::getAdapter()->insert($tableName, $this->_getChangedOwnFields());
        $this->_fields['id'] = self::getAdapter()->getLastInsertId();
        $this->_updateRelatedCounters();
        $this->callTrigger(self::SAVED, [$changedKeys]);
        $this->callTrigger(self::SAVE_CREATED, [$changedKeys]);
        return $this->_applyChanges();
    }

    /**
     * @return static
     */
    public function drop() {
        if ($this->callTrigger(self::DELETE) === false) {
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
        $this->_updateRelatedCounters(false);
        $id = $this->_fields['id'];
        $this->_fields['id'] = 0;
        $this->callTrigger(self::DELETED, [$id]);
        return $this;
    }

    /**
     * @param null $lifetime
     * @return static
     */
    public function cache($lifetime = null) {
        $cacher = self::getCacheProvider();
        $cacher->storeRecord($this, $lifetime);
        return $this;
    }


    ////////// Class relations manipulation
    /**
     * @param $class
     * @return bool|int
     */
    public function getRelationTypeWith($class) {
        if ($class instanceof Record) {
            $class = Helper::qualifyClassName(get_class($class));
        }
        return self::getLoader()->getClassRelationTypeWith($this->_getContext(), $class);
    }

    /**
     * @param $class
     * @return mixed|null
     * @throws RecordsManException
     */
    public function getRelationParamsWith($class) {
        return self::getLoader()->getClassRelationParamsWith($this->_getContext(), $class);
    }

    /**
     * @param $className
     * @return mixed
     * @throws RecordsManException
     */
    public function loadForeign($className) {
        $foreignClass = Helper::qualifyClassName($className);
        if (isset($this->_foreign[$foreignClass])) {
            return $this->_foreign[$foreignClass];
        }
        $relationType = $this->getRelationTypeWith($foreignClass);
        $relationParams = $this->getRelationParamsWith($foreignClass);
        /** @var Record|string $foreignClass */
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

    /**
     * @param $className
     * @param $records
     * @return static
     * @throws RecordsManException
     */
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
                $this->set($field, $records->id);
                break;
            case self::RELATION_MANY:
                /** @var Record $record */
                foreach ($records as $record) {
                    $record->setForeign($context, $this);
                }
                break;
            default:
                throw new RecordsManException("Class {$context} hasn't relation with {$foreignClass}", 10);
        }
        return $this;
    }


    ////////// Magic
    /**
     * @param string $fieldName
     * @return mixed|null
     */
    public function __get($fieldName) {
        return $this->get($fieldName);
    }

    /**
     * @param string $fieldName
     * @param $value
     * @throws RecordsManException
     */
    public function __set($fieldName, $value) {
        $context = $this->_getContext();
        $msg = "Field {$context}::{$fieldName} was not declared as public (use TPublicFields to declare public fields)";
        throw new RecordsManException($msg, 41);
    }


    ////////// Loader related methods
    /**
     * @param Loader $loader
     */
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
    /**
     * @return array
     */
    final public static function getMetaData() {
        $called = get_called_class();
        $tableName = isset(static::$tableName) ? static::$tableName : Helper::extractTableNameFromClassName($called);
        $hasMany = [];
        $belongsTo = [];
        if (isset(static::$hasMany) && is_array(static::$hasMany)) {
            foreach (static::$hasMany as $class => $relationParams) {
                $hasMany[$class] = is_array($relationParams) ? $relationParams : ['foreignKey' => $relationParams];
            }
        }
        if (isset(static::$belongsTo) && is_array(static::$belongsTo)) {
            foreach (static::$belongsTo as $class => $relationParams) {
                $belongsTo[$class] = is_array($relationParams) ? $relationParams : ['foreignKey' => $relationParams];
            }
        }
        return [
            'tableName' => $tableName,
            'hasMany'   => $hasMany,
            'belongsTo' => $belongsTo
        ];
    }

    /**
     * @return array
     */
    final public static function getDefaultTriggersList() {
        return [
            self::SAVE,
            self::SAVE_UPDATE,
            self::SAVE_CREATE,
            self::SAVED,
            self::SAVE_UPDATED,
            self::SAVE_CREATED,
            self::DELETE,
            self::DELETED
        ];
    }

    /**
     * @return string
     */
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
        if (!isset($this->_context)) {
            $this->_context = Helper::qualifyClassName(get_class($this));
        }
        return $this->_context;
    }

    private function _deleteRelatedRecords() {
        $context = $this->_getContext();
        $loader = self::getLoader();
        $relatedClasses = $loader->getClassRelations($context, Record::RELATION_MANY);
        if (!empty($relatedClasses)) {
            foreach ($relatedClasses as $relClass) {
                $relationParams = $loader->getClassRelationParamsWith($context, $relClass);
                if (isset($relationParams['through'])) {
                    continue;
                }
                /** @var Record $foreign */
                foreach ($this->loadForeign($relClass) as $foreign) {
                    $foreign->drop();
                }
            }
        }
    }

    private function _updateRelatedCounters($checkChanged = true) {
        $context = $this->_getContext();
        $loader = self::getLoader();
        $classesWithCounters = $loader->getClassCounters($context);
        $thisTab = $loader->getClassTableName($context);
        /** @var Record $className */
        foreach ($classesWithCounters as $className => $counterField) {
            $relationParams = $loader->getClassRelationParamsWith($context, $className);
            $foreignKey = $relationParams['foreignKey'];
            if ($checkChanged && !isset($this->_changed[$foreignKey])) {
                continue;
            }
            $newForeignItemId = isset($this->_changed[$foreignKey]) ? $this->_changed[$foreignKey] : null;
            $prevForeignItemId = isset($this->_fields[$foreignKey]) ? $this->_fields[$foreignKey] : null;
            $parentTab = $loader->getClassTableName($className);
            if ($newForeignItemId) {
                // Updating new foreign item
                $sql = "UPDATE `{$parentTab}` SET `{$counterField}` = (";
                $sql.= "SELECT COUNT(*) FROM `{$thisTab}` WHERE `{$foreignKey}`={$newForeignItemId}";
                $sql.= ") WHERE `id`={$newForeignItemId} LIMIT 1";
                self::getAdapter()->query($sql);
            }
            if ($prevForeignItemId) {
                // Updating previous foreign item
                $sql = "UPDATE `{$parentTab}` SET `{$counterField}` = (";
                $sql.= "SELECT COUNT(*) FROM `{$thisTab}` WHERE `{$foreignKey}`={$prevForeignItemId}";
                $sql.= ") WHERE `id`={$prevForeignItemId} LIMIT 1";
                self::getAdapter()->query($sql);
            }
        }
    }

    public function _checkForeignKeys() {
        $context = $this->_getContext();
        $loader = $this->getLoader();
        $relatedClasses = $loader->getClassRelations($context, Record::RELATION_BELONGS);
        /** @var Record $className */
        foreach ($relatedClasses as $className) {
            if ($context == $className) {
                // Skipping self-related classes
                continue ;
            }
            $relationParams = $this->getRelationParamsWith($className);
            $foreignItemId = $this->get($relationParams['foreignKey']);
            try {
                $className::load($foreignItemId);
            } catch (\Exception $e) {
                $msg = "Related item of class {$className} (#{$foreignItemId}) are not exists";
                throw new RecordsManException($msg, 85);
            }
        }
    }

    private function _lookupForeignByFieldname($context, $fieldname) {
        $foreignClass = ucfirst(Helper::getSingular($fieldname));
        $lookup = function($foreignClass, $relations) {
            foreach ($relations as $relatedName) {
                $chunks = explode('\\', $relatedName);
                $className = $chunks[count($chunks)-1];
                if ($className == $foreignClass) {
                    return $relatedName;
                }
            }
        };
        return $lookup(
            $foreignClass,
            self::getLoader()->getClassRelations($context, self::RELATION_MANY)
        ) ?: $lookup(
            $foreignClass,
            self::getLoader()->getClassRelations($context, self::RELATION_BELONGS)
        );
    }

    private function _applyChanges() {
        foreach ($this->_changed as $fieldName => $value) {
            $this->_fields[$fieldName] = $value;
        }
        $this->_changed = [];
        return $this;
    }

    private function _getChangedOwnFields() {
        $fields = [];
        foreach ($this->_changed as $key => $v) {
            if ($this->hasOwnField($key)) {
                $fields[$key] = $v;
            }
        }
        return $fields;
    }
}
