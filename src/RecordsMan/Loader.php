<?php
namespace RecordsMan;


class Loader {

    private $_adapter = null;
    private $_cacher = null;
    private $_tables = [];
    private $_classes = [];

    public function __construct(IDBAdapter $adapter) {
        $this->setAdapter($adapter);
    }

    public function setAdapter(IDBAdapter $adapter) {
        $this->_adapter = $adapter;
    }

    /**
     * @return IDBAdapter
     */
    public function getAdapter() {
        return $this->_adapter;
    }

    /**
     * @param IRecordsCacher $cacher
     * @return self
     */
    public function setCacheProvider(IRecordsCacher $cacher) {
        $this->_cacher = $cacher;
        return $this;
    }

    /**
     * @return IRecordsCacher
     */
    public function getCacheProvider() {
        return $this->_cacher;
    }

    public function registerClass($className) {
        $qualifiedName = Helper::qualifyClassName($className);
        if (isset($this->_classes[$qualifiedName])) {
            return $qualifiedName;
        }
        if (!class_exists($qualifiedName)) {
            throw new RecordsManException("Class {$qualifiedName} are not exists", 5);
        }
        $classMeta = $qualifiedName::getMetaData();
        if (!$this->isTableExists($classMeta['tableName'])) {
            throw new RecordsManException("Table {$classMeta['tableName']} are not exists", 25);
        }
        $classMeta['fields'] = [];
        $classMeta['triggers'] = [];
        $classMeta['properties'] = [];
        foreach($this->getAdapter()->getTableColumns($classMeta['tableName']) as $columnDef) {
            //TODO: auto detect primary key
            $classMeta['fields'][$columnDef['Field']] = $columnDef['Default'];
        }
        $this->_classes[$qualifiedName] = $classMeta;
        if (method_exists($qualifiedName, 'init')) {
            $qualifiedName::init();
        }
        return $qualifiedName;
    }

    public function getFieldsDefinition($className) {
        $qualifiedName = $this->registerClass($className);
        return $this->_classes[$qualifiedName]['fields'];
    }

    public function getClassRelationTypeWith($class, $withClass) {
        $qualifiedClass = $this->registerClass($class);
        $qualifiedWith = $this->registerClass($withClass);
        $meta = $this->_classes[$qualifiedClass];
        if (isset($meta['hasMany'][$qualifiedWith])) {
            return Record::RELATION_MANY;
        }
        // reverse checking
        $meta = $this->_classes[$qualifiedWith];
        if (isset($meta['hasMany'][$qualifiedClass])) {
            return Record::RELATION_BELONGS;
        }
        return Record::RELATION_NONE;
    }

    public function getClassRelationParamsWith($class, $withClass) {
        $relationType = $this->getClassRelationTypeWith($class, $withClass);
        $qualifiedClass = $this->registerClass($class);
        $qualifiedWith = Helper::qualifyClassName($withClass);
        switch ($relationType) {
            case Record::RELATION_BELONGS:
                $meta = $this->_classes[$qualifiedWith];
                return $meta['hasMany'][$qualifiedClass];
            case Record::RELATION_MANY:
                $meta = $this->_classes[$qualifiedClass];
                return $meta['hasMany'][$qualifiedWith];
            case Record::RELATION_NONE:
                throw new RecordsManException("Class {$qualifiedClass} hasn't relation with {$qualifiedWith}", 10);
        }
        return null;
    }

    public function getClassRelations($className, $relationType = Record::RELATION_MANY) {
        $qualifiedClass = $this->registerClass($className);
        $key = ($relationType == Record::RELATION_BELONGS) ? 'belongsTo' : 'hasMany';
        return array_keys(
            $this->_classes[$qualifiedClass][$key]
        );
    }

    public function getClassCounters($className, $skipClass = null) {
        $qualifiedClass = $this->registerClass($className);
        $belongsTo = $this->getClassRelations($qualifiedClass, Record::RELATION_BELONGS);
        $classesWithCounters = [];
        foreach($belongsTo as $parentClassName) {
            $relationParams = $this->getClassRelationParamsWith($parentClassName, $qualifiedClass);
            if (array_key_exists('counter', $relationParams) && ($parentClassName != $skipClass)) {
                $classesWithCounters[$parentClassName] = $relationParams['counter'];
            }
        }
        return $classesWithCounters;
    }

    public function getClassTableName($className) {
        $qualifiedClass = $this->registerClass($className);
        return $this->_classes[$qualifiedClass]['tableName'];
    }

    public function isTableExists($tableName) {
        $this->_loadTables();
        return in_array($tableName, $this->_tables);
    }

    public function isFieldExists($className, $fieldName) {
        $qualifiedName = $this->registerClass($className);
        return array_key_exists($fieldName, $this->_classes[$qualifiedName]['fields']);
    }

    public function addClassTrigger($className, $triggerType, \Closure $callback) {
        $qualifiedName = $this->registerClass($className);
        if (!isset($this->_classes[$qualifiedName]['triggers'][$triggerType])) {
            $this->_classes[$qualifiedName]['triggers'][$triggerType] = [];
        }
        $this->_classes[$qualifiedName]['triggers'][$triggerType][] = $callback;
    }

    public function getClassTriggersCallbacks($className, $triggerName) {
        $qualifiedName = $this->registerClass($className);
        if (!isset($this->_classes[$qualifiedName]['triggers'][$triggerName])) {
            return [];
        }
        return $this->_classes[$qualifiedName]['triggers'][$triggerName];
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @param null|\Closure $getter
     * @param null|\Closure $setter
     */
    public function addClassProperty($className, $propertyName, $getter = null, $setter = null) {
        $qualifiedName = $this->registerClass($className);
        if (!isset($this->_classes[$qualifiedName]['properties'][$propertyName])) {
            $this->_classes[$qualifiedName]['properties'][$propertyName]['getters'] = [];
            $this->_classes[$qualifiedName]['properties'][$propertyName]['setters'] = [];
        }
        if ($getter instanceof \Closure) {
            $this->_classes[$qualifiedName]['properties'][$propertyName]['getters'][] = $getter;
        }
        if ($setter instanceof \Closure) {
            $this->_classes[$qualifiedName]['properties'][$propertyName]['setters'][] = $setter;
        }
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @return bool
     */
    public function hasClassPropertyGetterCallbacks($className, $propertyName) {
        $qualifiedName = Helper::qualifyClassName($className);
        return isset($this->_classes[$qualifiedName]['properties'][$propertyName]['getters']) &&
            !empty($this->_classes[$qualifiedName]['properties'][$propertyName]['getters']);
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @return array array of \Closure(s) or empty array
     */
    public function getClassPropertyGetterCallbacks($className, $propertyName) {
        $qualifiedName = $this->registerClass($className);
        return isset($this->_classes[$qualifiedName]['properties'][$propertyName]['getters']) ?
            $this->_classes[$qualifiedName]['properties'][$propertyName]['getters'] : [];
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @return bool
     */
    public function hasClassPropertySetterCallbacks($className, $propertyName) {
        $qualifiedName = Helper::qualifyClassName($className);
        return isset($this->_classes[$qualifiedName]['properties'][$propertyName]['setters']) &&
            !empty($this->_classes[$qualifiedName]['properties'][$propertyName]['setters']);
    }

    /**
     * @param string $className
     * @param string $propertyName
     * @return array array of \Closure(s) or empty array
     */
    public function getClassPropertySetterCallbacks($className, $propertyName) {
        $qualifiedName = $this->registerClass($className);
        return isset($this->_classes[$qualifiedName]['properties'][$propertyName]['setters']) ?
            $this->_classes[$qualifiedName]['properties'][$propertyName]['setters'] : [];
    }

    private function _loadTables() {
        if (empty($this->_tables)) {
            $this->_tables = $this->getAdapter()->getTables();
        }
        return $this->_tables;
    }

}
