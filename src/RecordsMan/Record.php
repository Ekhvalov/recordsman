<?php
namespace RecordsMan;

abstract class Record {

    const RELATION_NONE = false;
    const RELATION_BELONGS = 1;
    const RELATION_MANY = 2;

    private static $_loader = null;
    private static $_triggers = [];

    private $_fields = [];
    private $_foreign = [];
    private $_changed = false;


    ////////// Records loading static methods

    /**
     * Loads an single instance by primary key
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
     * Finds & returns all records in table. Shortcut for find() [without params]
     *
     * @return RecordSet
     */
    public static function all() {
        return static::find();
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


    ////////// Triggers

    final public static function addTrigger($triggerName, \Closure $callback) {
        if (!isset(self::$_triggers[$triggerName])) {
            self::$_triggers[$triggerName] = [];
        }
        self::$_triggers[$triggerName][] = $callback;
    }

    final public function callTrigger($triggerName, $argsArray = []) {
        if (!isset(self::$_triggers[$triggerName])) {
            return null;
        }
        $result = null;
        foreach(self::$_triggers[$triggerName] as $callback) {
            $result = call_user_func_array($callback->bindTo($this), $argsArray);
            if ($result === false) {
                break;
            }
        }
        return $result;
    }


    ////////// Fields manipulating methods

    public function get($fieldName) {
        if (array_key_exists($fieldName, $this->_fields)) {
            return $this->_fields[$fieldName];
        }
        $context = $this->_getContext();
        $foreignClass = Helper::getClassNamespace($context) . ucfirst(Helper::getSingular($fieldName));
        try {
            $foreign = $this->loadForeign($foreignClass);
            return $foreign;
        } catch(\Exception $e) {

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
        if ($fieldNameOrFieldsArray == 'id') {
            throw new RecordsManException("Can't change `id` field", 70);
        }
        $this->_fields[$fieldNameOrFieldsArray] = $value;
        if ($this->hasOwnField($fieldNameOrFieldsArray)) {
            $this->_changed = true;
        }
        return $this;
    }

    public function hasOwnField($fieldName) {
        return self::getLoader()->isFieldExists($this->_getContext(), $fieldName);
    }

    public function toArray() {
        return $this->_fields;
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
        //TODO: exception throwing if rows are empty
        $this->_fields = $rows[0];
        return $this;
    }

    public function save($testRelations = true) {
        $thisId = $this->get('id');
        $context = $this->_getContext();
        $tableName = self::getLoader()->getClassTableName($context);
        $actualFields = [];
        // filtering only own fields
        foreach($this->_fields as $fieldName => $value) {
            if ( ($fieldName != 'id') && $this->hasOwnField($fieldName) ) {
                $actualFields[$fieldName] = $value;
            }
        }
        if ($testRelations) {
            $this->_checkForeignKeys();
        }
        if ($thisId) {
            // updating existing entry
            //TODO: `updated_at` autofill
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
        //TODO: `created_at` autofill
        self::getAdapter()->insert($tableName, $actualFields);
        $this->_fields['id'] = self::getAdapter()->getLastInsertId();
        $this->_updateRelatedCounters();
        return $this;
    }

    public function drop() {
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
                $this->_foreign[$foreignClass] = $foreignClass::load($this->get($relationParams['foreignKey']));
                break;
            case self::RELATION_MANY:
                $this->_foreign[$foreignClass] = RecordSet::createFromForeign($this, $foreignClass);
                break;
        }
        return $this->_foreign[$foreignClass];
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
            'belongsTo' => $belongsTo
        ];
    }


    ////////// Closed methods

    protected function __construct($fields) {
        if (!is_array($fields)) {
            //TODO: Exception throwing
        }
        $this->_fields = $fields;
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
