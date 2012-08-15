<?php
namespace RecordsMan;

trait TPreload {

    protected static $_preloaded = null;

    public static function _select($condition = null, $order = null, $limit = null) {
        if (is_null(self::$_preloaded)) {
            self::$_preloaded = parent::_select();
        }
        //TODO: $order & $limit processing
        if (is_null($condition)) {
            return self::$_preloaded;
        }
        $condition = Condition::create($condition);
        $resultSet = [];
        foreach(self::$_preloaded as $entry) {
            if ($condition->test($entry)) {
                $resultSet[] = $entry;
            }
        }
        return $resultSet;
    }

    public static function flushCache() {
        self::$_preloaded = null;
    }

}
