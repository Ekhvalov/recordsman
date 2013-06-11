<?php

namespace RecordsMan;


class WPCacheAdapter implements IRecordsCacher {

    private $_cacher = null;
    private $_prefix = 'recordsman';

    public function __construct(\WP\Caching\ICacheProvider $wpCacheProvider) {
        $this->_cacher = $wpCacheProvider;
    }

    public function getRecord($class, $id) {
        $key = $this->_buildItemKey($class, $id);
        if ($this->_cacher->isCacheAvailable($key)) {
            return $this->_cacher->readData($key);
        }
        return null;
    }

    public function getRecordSet($class, $key) {
        $fullKey = $this->_buildSetKey($class, $key);
        if ($this->_cacher->isCacheAvailable($fullKey)) {
            return $this->_cacher->readData($fullKey);
        }
        return null;
    }

    public function storeRecord(Record $item, $lifetime = null) {
        if (!$item->id) {
            $item->save();
        }
        $class = $item->getQualifiedClassname();
        $key = $this->_buildItemKey($class, $item->id);
        $this->_cacher->writeData($key, $item->toArray(), $lifetime);
    }

    public function storeRecordSet(RecordSet $set, $key, $lifetime = null) {
        $class = $set->getClassName();
        $fullKey = $this->_buildSetKey($class, $key);
        $ids = [];
        foreach($set as $item) {
            $ids[] = $item->id;
            $this->storeRecord($item, $lifetime);
        }
        $this->_cacher->writeData($fullKey, $ids, $lifetime);
    }

    private function _buildItemKey($class, $id) {
        return "{$this->_prefix}}:{$class}:{$id}";
    }

    private function _buildSetKey($class, $key) {
        return "{$this->_prefix}:{$class}:{$key}";
    }

}
