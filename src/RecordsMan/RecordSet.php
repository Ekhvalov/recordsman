<?php
namespace RecordsMan;

class RecordSet implements \Iterator, \Countable, \ArrayAccess {

    const LOAD_BY_SQL = 1;
    const LOAD_FROM_RELATION = 2;
    const LOAD_FROM_CACHE = 3;
    const LOAD_FROM_FILTER = 4;
    const LOAD_BY_CONDITION = 5;

    private $_loadMode = 0;

    private $_loadingParams = [
        'class'     => '',
        'fields'    => [],
        'loaded'    => false,
        'loadBy'    => [],
        'initiator' => null,
        'count'     => null
    ];
    private $_records = [];


    ////////// Opened constructors

    public static function create($class, $condition = null, $order = null, $limit = null) {
        $set = new self(
            $class,
            ['params' => $condition, 'order' => $order, 'limit' => $limit]
        );
        $set->_loadMode = self::LOAD_BY_CONDITION;
        return $set;
    }

    public static function createFromSql($class, $sqlQuery, $sqlParams = []) {
        $set = new self(
            $class,
            ['sql' => $sqlQuery, 'sqlParams' => $sqlParams]
        );
        $set->_loadMode = self::LOAD_BY_SQL;
        return $set;
    }

    public static function createFromForeign(Record $record, $foreignClass) {
        $foreignClass = Helper::qualifyClassName($foreignClass);
        if ($record->getRelationTypeWith($foreignClass) != Record::RELATION_MANY) {
            throw new RecordsManException("Relation type must be Record::RELATION_MANY", 50);
        }
        $relationParams = $record->getRelationParamsWith($foreignClass);
        $set = new self(
            $foreignClass,
            ['relation' => $relationParams],
            $record
        );
        $set->_loadMode = self::LOAD_FROM_RELATION;
        return $set;
    }

    public static function createFromCache($class, $key) {
        $cacher = Record::getCacheProvider();
        $ids = $cacher->getRecordSet($class, $key);
        if (is_null($ids)) {
            return null;
        }
        $set = new self(
            $class,
            ['cache' => $key, 'ids' => $ids]
        );
        $set->_loadMode = self::LOAD_FROM_CACHE;
        return $set;
    }

    public static function createEmpty($class) {
        $set = new self($class, 'filter');
        $set->_loadingParams['loaded'] = true;
        $set->_loadMode = self::LOAD_FROM_FILTER;
        return $set;
    }

    public function getClassName() {
        return $this->_loadingParams['class'];
    }


    ////////// Records manipulation methods

    public function save($testRelations = true) {
        return $this->map(function($_, Record $entry) use ($testRelations) {
            $entry->save($testRelations);
        });
    }

    public function reload() {
        $this->_loadRecords(true);
        return $this;
    }

    public function add($entries) {
        if ($entries instanceof self) {
            foreach($entries as $entry) {
                $this->add($entry);
            }
            return $this;
        }
        if (!$entries instanceof Record) {
            throw new \InvalidArgumentException("Appended entry should be an instance of Record", 55);
        }
        if (is_null($this->_loadingParams['initiator'])) {
            throw new RecordsManException("Can't add new entry to set which hasn't initiator record", 60);
        }
        $entry = $entries;
        $initiator = $this->_loadingParams['initiator'];
        if (!$initiator->get('id')) {
            $initiator->save();
        }
        $thisClass = $this->_loadingParams['class'];
        $initiatorClass = Helper::qualifyClassName(get_class($initiator));
        $relationType = Record::getLoader()->getClassRelationTypeWith($initiatorClass, $thisClass);
        if ($relationType != Record::RELATION_MANY) {
            throw new RecordsManException("Class {$initiatorClass} hasn't HAS_MANY relation with {$thisClass}", 65);
        }
        $loader = Record::getLoader();
        $relationParams = $loader->getClassRelationParamsWith($initiatorClass, $thisClass);
        if (isset($relationParams['through'])) {
            // Many-to-many relation
            //TODO: tests
            $throughClass = $relationParams['through'];
            $this_through_Relation = $loader->getClassRelationParamsWith($thisClass, $throughClass);
            $initiator_through_Relation = $loader->getClassRelationParamsWith($initiatorClass, $throughClass);
            if (!$entry->get('id')) {
                $entry->save();
            }
            $throughItem = null;
            $counter = isset($relationParams['counter']) ? $relationParams['counter'] : false;
            $isNew = false;
            if (isset($relationParams['unique']) && $relationParams['unique']) {
                $condition = [
                    ($initiator_through_Relation['foreignKey'] . '=' . $initiator->get('id')),
                    ($this_through_Relation['foreignKey'] . '=' . $entry->get('id'))
                ];
                $throughItem = $throughClass::findFirst($condition);
            }
            if (is_null($throughItem)) {
                $throughItem = $throughClass::create();
                $throughItem->setForeign($thisClass, $entry);
                $throughItem->setForeign($initiatorClass, $initiator);
                $isNew = true;
            }
            if ($counter) {
                $throughItem->set($relationParams['counter'], $isNew ? 1 : ($throughItem->$counter + 1));
            }
            $this->_loadingParams['loaded'] = false;
            if (isset($this->_loadingParams['count'])) {
                unset($this->_loadingParams['count']);
            }
            return $throughItem->save();
        } else {
            // One-to-many relation
            $entry->setForeign($initiatorClass, $initiator)->save();
            if (isset($this->_loadingParams['count'])) {
                unset($this->_loadingParams['count']);
            }
            if ($this->_loadingParams['loaded']) {
                $this->_records[] = $entry;
                $this->_loadingParams['count'] = count($this->_records);
            }
        }
        return $this;
    }

    public function map(\Closure $callback) {
        $this->_loadRecords();
        foreach($this->_records as $index => $record) {
            if ($callback($index, $record) === false) {
                break;
            }
        }
        return $this;
    }

    public function filter($conditionOrCallback) {
        $this->_loadRecords();
        return ($conditionOrCallback instanceof \Closure)
            ? $this->_filterByCallback($conditionOrCallback)
            : $this->_filterByCondition($conditionOrCallback);
    }

    public function filterFirst($conditionOrCallback) {
        $this->_loadRecords();
        return ($conditionOrCallback instanceof \Closure)
            ? $this->_filterByCallback($conditionOrCallback, true)
            : $this->_filterByCondition($conditionOrCallback, true);
    }

    public function isEmpty() {
        return ($this->count() == 0);
    }

    public function toArray($neededFields = []) {
        $this->_loadRecords();
        $res = [];
        foreach($this->_records as $item) {
            $res[] = $item->toArray($neededFields);
        }
        return $res;
    }

    public function cache($key, $lifetime = null) {
        $cacher = Record::getCacheProvider();
        $cacher->storeRecordSet($this, $key, $lifetime);
        return $this;
    }

    /**
     * @return Record
     */
    public function shift() {
        $this->_loadRecords();
        $entry = array_shift($this->_records);
        $this->_loadingParams['count'] = count($this->_records);
        return $entry;
    }

    /**
     * @param Record $item
     * @return RecordSet
     * @throws RecordsManException
     */
    public function prepend(Record $item) {
        if ($item->getQualifiedClassname() != $this->getClassName()) {
            throw new RecordsManException("Can't prepend set of {$this->getClassName()} with item of class {$item->getQualifiedClassname()}");
        }
        array_unshift($this->_records, $item);
        $this->_loadingParams['count'] = count($this->_records);
        return $this;
    }

    /**
     * @return Record
     */
    public function pop() {
        $this->_loadRecords();
        $entry = array_pop($this->_records);
        $this->_loadingParams['count'] = count($this->_records);
        return $entry;
    }

    /**
     * @param Record $item
     * @return RecordSet
     * @throws RecordsManException
     */
    public function append(Record $item) {
        if ($item->getQualifiedClassname() != $this->getClassName()) {
            throw new RecordsManException("Can't append item of class {$item->getQualifiedClassname()} to set of {$this->getClassName()}");
        }
        $this->_records[] = $item;
        $this->_loadingParams['count'] = count($this->_records);
        return $this;
    }

    /**
     * @param $count
     * @param int $from
     * @return RecordSet
     */
    public function slice($count, $from = 0) {
        $this->_loadRecords();
        $newSet = $this->_newSelf();
        $newSet->_loadingParams['loaded'] = 1;
        $newSet->_records = array_slice($this->_records, $from, $count);
        return $newSet;
    }


    ////////// Countable implementation

    public function count() {
        if (isset($this->_loadingParams['count'])) {
            return $this->_loadingParams['count'];
        }
        $count = $this->_tryToPrefetchCount();
        if (!is_null($count)) {
            fprintf(STDERR, "[Count prefetched]\n");
            return $count;
        }
        $this->_loadRecords();
        return count($this->_records);
    }


    ////////// ArrayAccess implementation

    public function offsetExists($key) {
        $this->_loadRecords();
        return isset($this->_records[$key]);
    }

    public function offsetGet($key) {
        if ($this->offsetExists($key)) {
            return $this->_records[$key];
        }
        return null;
    }

    public function offsetSet($key, $val) {
        throw new RecordsManException("Can't change entries in set", 70);
    }

    public function offsetUnset($key) {
        if ($this->offsetExists($key)) {
            unset($this->_records[$key]);
        }
    }


    ////////// Iterator implementation

    public function rewind() {
        $this->_loadRecords();
        return reset($this->_records);
    }

    public function next() {
        $this->_loadRecords();
        return next($this->_records);
    }

    public function valid() {
        return $this->current();
    }

    public function key() {
        $this->_loadRecords();
        return key($this->_records);
    }

    public function current() {
        $this->_loadRecords();
        return current($this->_records);
    }


    ////////// Closed methods

    private function __construct($class, $loadBy, $initiator = null) {
        if (!is_null($initiator)) {
            $this->_loadingParams['initiator'] = $initiator;
            //TODO: count cache ?
        }
        $this->_loadingParams['class'] = $class;
        $this->_loadingParams['fields'] = Record::getLoader()->getFieldsDefinition($class);
        $this->_loadingParams['loadBy'] = $loadBy;
    }

    private function _loadRecords($forceLoading = false) {
        if ($this->_loadingParams['loaded'] && !$forceLoading) {
            return true;
        }
        fprintf(STDERR, "[Records loading]\n");
        $targetClass = $this->_loadingParams['class'];
        $loadBy = $this->_loadingParams['loadBy'];
        $rows = [];

        switch($this->_loadMode) {

            case self::LOAD_BY_SQL:
                $rows = Record::getAdapter()->fetchRows(
                    $loadBy['sql'],
                    isset($loadBy['sqlParams']) ? $loadBy['sqlParams'] : []
                );
                break;

            case self::LOAD_FROM_RELATION:
                $rows = $this->_loadRowsByRelation();
                break;

            case self::LOAD_FROM_CACHE:
                $cacher = Record::getCacheProvider();
                foreach($loadBy['ids'] as $id) {
                    //TODO: Warning! every record may not exists in the cache!
                    $rows[] = $cacher->getRecord($targetClass, $id);
                }
                break;

            case self::LOAD_BY_CONDITION:
                $params = isset($loadBy['params']) ? $loadBy['params'] : null;
                $order = isset($loadBy['order']) ? $loadBy['order'] : null;
                $limit = isset($loadBy['limit']) ? $loadBy['limit'] : null;
                $rows = call_user_func_array([$targetClass, '_select'], [$params, $order, $limit]);
                break;
        }

        $this->_records = [];
        if (!empty($rows)) {
            foreach($rows as $row) {
                $this->_records[] = $targetClass::_fromArray($row);
            }
        }
        $this->_loadingParams['loaded'] = true;
        $this->_loadingParams['count'] = count($rows);
        return $this->_loadingParams['count'];
    }

    private function _loadRowsByRelation($countOnly = false) {
        $targetClass = $this->_loadingParams['class'];
        $loadBy = $this->_loadingParams['loadBy'];
        $relationParams = $loadBy['relation'];
        $srcRecord = $this->_loadingParams['initiator'];
        if (isset($relationParams['through'])) {
            // Many-to-many relation
            $loader = Record::getLoader();
            $srcClass = Helper::qualifyClassName(get_class($srcRecord));
            $throughClass = $relationParams['through'];
            $throughTab = $loader->getClassTableName($throughClass);
            $targetTab = $loader->getClassTableName($targetClass);
            $targetThroughRelationParams = $loader->getClassRelationParamsWith($targetClass, $throughClass);
            $targetThroughForeignKey = $targetThroughRelationParams['foreignKey'];
            $scrThroughRelationParams = $loader->getClassRelationParamsWith($srcClass, $throughClass);
            $srcThroughForeignKey = $scrThroughRelationParams['foreignKey'];
            //TODO: how to define field prefix in condition?
            $queryParams = "{$srcThroughForeignKey}={$srcRecord->get('id')}";
            if (array_key_exists('condition', $scrThroughRelationParams)) {
                $queryParams = Condition::createAndBlock([$queryParams, $scrThroughRelationParams['condition']]);
            }
            if ($countOnly) {
                $sql = Helper::createSelectCountJoinQuery(
                    $targetTab,
                    $throughTab,
                    $targetThroughForeignKey,
                    $queryParams
                );
                return intval(Record::getAdapter()->fetchSingleValue($sql));
            }
            $sql = Helper::createSelectJoinQuery(
                $targetTab,
                $throughTab,
                $targetThroughForeignKey,
                $queryParams
            );
            return Record::getAdapter()->fetchRows($sql);
        }
        // One-to-many relation
        $queryParams = "{$relationParams['foreignKey']}={$srcRecord->get('id')}";
        if (array_key_exists('condition', $relationParams)) {
            $queryParams = Condition::createAndBlock([$queryParams, $relationParams['condition']]);
        }
        if ($countOnly) {
            return call_user_func_array([$targetClass, 'count'], [$queryParams]);
        }
        return call_user_func_array([$targetClass, '_select'], [$queryParams]);
    }

    private function _tryToPrefetchCount() {
        $targetClass = $this->_loadingParams['class'];
        $loadBy = $this->_loadingParams['loadBy'];
        switch($this->_loadMode) {

            case self::LOAD_BY_CONDITION:
                $params = isset($loadBy['params']) ? $loadBy['params'] : null;
                $totalCount = call_user_func_array([$targetClass, 'count'], [$params]);
                if ($totalCount == 0) {
                    $this->_loadingParams['count'] = 0;
                    return 0;
                }
                if (isset($loadBy['limit'])) {
                    list($from, $count) = $this->_limitAsRange($loadBy['limit']);
                    if (($from + $count) > $totalCount) {
                        $count = $totalCount - $from;
                    }
                    $this->_loadingParams['count'] = $count;
                    return $count;
                }
                $this->_loadingParams['count'] = $totalCount;
                return $totalCount;

            case self::LOAD_FROM_RELATION:
                $this->_loadingParams['count'] = $this->_loadRowsByRelation(true);
                return $this->_loadingParams['count'];

            case self::LOAD_FROM_CACHE:
                $this->_loadingParams['count'] = count($loadBy['ids']);
                return $this->_loadingParams['count'];

            //TODO: self::LOAD_BY_SQL
        }
        return null;
    }

    private function _limitAsRange($limit) {
        if (is_array($limit)) {
            return $limit;
        }
        return [0, $limit];
    }

    private function _filterByCallback(\Closure $callback, $first = false) {
        $newSet = $this->_newSelf();
        $newSet->_loadingParams['loaded'] = true;
        foreach($this->_records as $record) {
            if (call_user_func($callback->bindTo($record))) {
                if ($first) {
                    return $record;
                }
                $newSet->_records[] = $record;
            }
        }
        return $first ? null : $newSet;
    }

    private function _filterByCondition($condition, $first = false) {
        $newSet = $this->_newSelf();
        $newSet->_loadingParams['loaded'] = true;
        foreach($this->_records as $record) {
            if ($record->isMatch($condition)) {
                if ($first) {
                    return $record;
                }
                $newSet->_records[] = $record;
            }
        }
        return $first ? null : $newSet;
    }

    private function _newSelf() {
        return new self($this->_loadingParams['class'], 'filter', $this->_loadingParams['initiator']);
    }

}
