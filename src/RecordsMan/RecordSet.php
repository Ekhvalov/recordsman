<?php
namespace RecordsMan;

class RecordSet implements \Iterator, \Countable, \ArrayAccess {


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
        return new self(
            $class,
            ['params' => $condition, 'order' => $order, 'limit' => $limit]
        );
    }

    public static function createFromSql($class, $sqlQuery, $sqlParams = []) {
        return new self(
            $class,
            ['sql' => $sqlQuery, 'sqlParams' => $sqlParams]
        );
    }

    public static function createFromForeign(Record $record, $foreignClass) {
        $foreignClass = Helper::qualifyClassName($foreignClass);
        if ($record->getRelationTypeWith($foreignClass) != Record::RELATION_MANY) {
            throw new RecordsManException("Relation type must be Record::RELATION_MANY", 50);
        }
        $relationParams = $record->getRelationParamsWith($foreignClass);
        return new self(
            $foreignClass,
            ['relation' => $relationParams],
            $record
        );
    }


    ////////// Records manipulation methods

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
                $throughItem = $throughClass::create([
                    $initiator_through_Relation['foreignKey'] => $initiator->get('id'),
                    $this_through_Relation['foreignKey'] => $entry->get('id')
                ]);
                $isNew = true;
            }
            if ($counter) {
                $throughItem->set($relationParams['counter'], $isNew ? 1 : ($throughItem->$counter + 1));
            }
            return $throughItem->save();
        } else {
            // One-to-many relation
            $entry->set($relationParams['foreignKey'], $initiator->get('id'))->save();
            if ($this->_loadingParams['loaded']) {
                $this->_records[] = $entry;
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

    //TODO: tests
    public function filterFirst($conditionOrCallback) {
        $this->_loadRecords();
        return ($conditionOrCallback instanceof \Closure)
            ? $this->_filterByCallback($conditionOrCallback, true)
            : $this->_filterByCondition($conditionOrCallback, true);
    }

    public function isEmpty() {
        return ($this->count() == 0);
    }

    //TODO: tests
    public function toArray($neededFields = []) {
        $this->_loadRecords();
        $res = [];
        foreach($this->_records as $item) {
            $res[] = $item->toArray($neededFields);
        }
        return $res;
    }


    ////////// Countable implementation

    public function count() {
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
        $targetClass = $this->_loadingParams['class'];
        $loadBy = $this->_loadingParams['loadBy'];
        if (isset($loadBy['sql'])) {
            $rows = Record::getAdapter()->fetchRows(
                $loadBy['sql'],
                isset($loadBy['sqlParams']) ? $loadBy['sqlParams'] : []
            );
        } elseif (isset($loadBy['relation'])) {
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
                $sql = Helper::createSelectJoinQuery(
                        $targetTab,
                        $throughTab,
                        $targetThroughForeignKey,
                        $queryParams
                );
                $rows = Record::getAdapter()->fetchRows($sql);
            } else {
                // One-to-many relation
                $queryParams = "{$relationParams['foreignKey']}={$srcRecord->get('id')}";
                if (array_key_exists('condition', $relationParams)) {
                    $queryParams = Condition::createAndBlock([$queryParams, $relationParams['condition']]);
                }
                $rows = call_user_func_array([$targetClass, '_select'], [$queryParams]);
            }
        } else {
            $params = isset($loadBy['params']) ? $loadBy['params'] : null;
            $order = isset($loadBy['order']) ? $loadBy['order'] : null;
            $limit = isset($loadBy['limit']) ? $loadBy['limit'] : null;
            $rows = call_user_func_array([$targetClass, '_select'], [$params, $order, $limit]);
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

    private function _filterByCallback(\Closure $callback, $first = false) {
        $newSet = $this->_newSelf();
        $newSet->_loadingParams['loaded'] = 1;
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
        $newSet->_loadingParams['loaded'] = 1;
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
