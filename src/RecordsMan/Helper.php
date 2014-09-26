<?php
namespace RecordsMan;

class Helper {

    /**
     * Преобразует имя класса $className в fully-qualified формат, если используются namespaces
     *
     * @param string $className
     * @return string
     */
    public static function qualifyClassName($className) {
        if (strpos($className, '\\')) {
            if ($className{0} != '\\') {
                $className = '\\'.$className;
            }
        }
        return $className;
    }

    /**
     * Ищет в названии класса $class namespace-часть.
     *
     * @param string $class
     * @return mixed возвращает namespace-часть, если таковая существует, иначе - пустую строку
     */
    public static function getClassNamespace($class) {
        if (strpos($class, '\\') !== false) {
            preg_match('#\\\\(.*)\\\\#', Helper::qualifyClassName($class), $matches);
            return $matches[0];
        }
        return '';
    }

    /**
     * Извлекает короткое имя класса из его fully-qualified name
     *
     * @param string $class
     * @return string
     */
    public static function extractClassName($class) {
        if (strpos($class, '\\') !== false) {
            preg_match('@(?P<short>\w+)$@', $class, $matches);
            return $matches['short'];
        }
        return $class;
    }

    public static function createSelectParams($condition = null, $order = null, $limit = null) {
        $sql = "";
        if (!is_null($condition)) {
            $sql.= " WHERE " . Condition::create($condition)->toSql();
        } else {
            $sql.= " WHERE 1";
        }
        if ($order) {
            $sql.= " ORDER BY " . Helper::orderToSql($order);
        }
        if ($limit) {
            $sql.= " LIMIT " . Helper::limitToSql($limit);
        }
        return $sql;
    }

    public static function createCountQuery($tableName, $condition = null) {
        $sql = "SELECT COUNT(*) FROM `{$tableName}` ";
        $sql.= self::createSelectParams($condition);
        return $sql;
    }

    public static function createSelectQuery($tableName, $condition = null, $order = null, $limit = null) {
        $sql = "SELECT * FROM `{$tableName}`";
        $sql.= self::createSelectParams($condition, $order, $limit);
        return $sql;
    }

    public static function createSelectJoinQuery($targetTab, $joinedTab, $foreignKey, $condition = null, $order = null, $limit = null) {
        $sql = "SELECT a.* FROM `{$targetTab}` AS a JOIN `{$joinedTab}` AS b ON a.`id`=b.`{$foreignKey}` ";
        $sql.= self::createSelectParams($condition, $order, $limit);
        return $sql;
    }

    public static function createSelectCountJoinQuery($targetTab, $joinedTab, $foreignKey, $condition = null, $order = null, $limit = null) {
        $sql = "SELECT COUNT(*) FROM `{$targetTab}` AS a JOIN `{$joinedTab}` AS b ON a.`id`=b.`{$foreignKey}` ";
        $sql.= self::createSelectParams($condition, $order, $limit);
        return $sql;
    }

    public static function createRandomSelectQuery($tableName, $condition = null, $limit = null) {
        $sql = "SELECT * FROM `{$tableName}` ";
        $sql.= self::createSelectParams($condition, 'RAND()', $limit);
        return $sql;
    }

    public static function selectQueryToCountQuery($sql) {
        $pattern = '@^\s*select\s+(?P<fields>.*)from(?P<etc>.+)(LIMIT\s+.+)?$@Uis';
        if (preg_match($pattern, $sql)) {
            return preg_replace($pattern, "SELECT COUNT(*) FROM\\2", $sql);
        }
        return null;
    }

    public static function extractLimitFromQuery($sql) {
        $pattern = '@limit\s+((?P<from>\d+)\s*,\s*)?(?P<cnt>\d+)\s*$@is';
        if (preg_match($pattern, $sql, $matches)) {
            return [preg_replace($pattern, '', $sql), [
                isset($matches['from']) ? intval($matches['from']) : 0,
                intval($matches['cnt'])
            ]];
        }
        return [$sql, null];
    }

    /**
     * Преобразует параметр $orderParam в строку для SQL-запроса
     *
     * @param mixed $orderParam Либо массив, где ключи - поля, а значения - порядок сортировки, либо имя поля
     * @return string
     */
    public static function orderToSql($orderParam) {
        $ret = '';
        if (is_array($orderParam)) {
            //TODO: correct values testing?
            foreach($orderParam as $k => $v) {
                $direction = strtoupper($v);
                $ret.= "`{$k}` {$direction}, ";
            }
            $ret = substr($ret, 0, strlen($ret)-2);
        } elseif ($orderParam) {
            $ret.= (strtoupper($orderParam) == 'RAND()') ? $orderParam : "`{$orderParam}`";
        }
        return $ret;
    }

    /**
     * Преобразует параметр $limitParam в строку для SQL-запроса
     *
     * @param mixed $limitParam Либо массив, в котором первое значение - начальная граница выборки, второе - кол-во записей, либо просто кол-во выбираемых записей
     * @return string
     */
    public static function limitToSql($limitParam) {
        if (is_array($limitParam)) {
            $ret = (isset($limitParam[0]) ? intval($limitParam[0]) : '0') . (isset($limitParam[1]) ? ','.intval($limitParam[1]) : '');
        } else {
            $ret = intval($limitParam);
        }
        return $ret;
    }

    /**
     * Добавить слова-исключения при образовании множ. числа
     *
     * @param array $words Массив сло в формате 'singular' => 'plural'
     */
    public static function addPluralizeExceptions($words) {
        if (is_array($words)) {
            self::$_pluralizeExceptions = $words + self::$_pluralizeExceptions;
        }
    }

    /**
     * Пытается определить единственное число для строки $str
     *
     * @param string $str
     * @return string
     */
    public static function getSingular($str) {
        if (preg_match('@(.*)([a-zA-Z]+)$@U', $str, $matches)) {
            $word = $matches[2];
            if ($complex = preg_match('@(\w+)([A-Z]\w+)$@U', $word, $matches1)) {
                $prefix = $matches1[1];
                $word = $matches1[2];
            }
            $ucFirst = preg_match('@^[A-Z]@', $word);
        } else {
            return $str;
        }
        $len = strlen($word);
        if ( FALSE !== ($key = array_search(strtolower($word), self::$_pluralizeExceptions)) ) {
            $word = $key;
        } elseif ( (substr($word, - 2, 2) == 'es') && ( in_array(substr($word, - 3, 1), self::$_oneLetEndings) || (in_array(substr($word, - 4, 2), self::$_twoLetEndings)) ) ) {
            $word = substr($word, 0, $len - 2);
        }
        elseif (substr($word,  - 3, 3) == 'ies') {
            $word = substr($word, 0, $len - 3).'y';
        }
        elseif ($word{$len - 1} == 's') {
            $word = substr($word, 0,$len - 1);
        }
        return $matches[1] . ($complex ? $prefix : '') . ($ucFirst ? ucfirst($word) : $word);
    }

    /**
     * Преобразует слово $str в множ. число
     *
     * @param string $str
     * @return string
     */
    public static function pluralize($str) {
        if (preg_match('@(.*)([a-zA-Z]+)$@U', $str, $matches)) {
            $word = $matches[2];
            if ($complex = preg_match('@(\w+)([A-Z]\w+)$@U', $word, $matches1)) {
                $prefix = $matches1[1];
                $word = $matches1[2];
            }
            $ucFirst = preg_match('@^[A-Z]@', $word);
        } else {
            return $str;
        }
        $len = strlen($word);
        $key = strtolower($word);
        if (isset(self::$_pluralizeExceptions[$key])) {
            $word = self::$_pluralizeExceptions[$key];
        }
        elseif (in_array(substr($word, - 1, 1), self::$_oneLetEndings) || in_array(substr($word, - 2, 2), self::$_twoLetEndings)) {
            $word = $word . 'es';
        }
        elseif ($word{$len - 1} == 'y') {
            $word = substr($word, 0, $len - 1) . 'ies';
        } else {
            $word = $word . 's';
        }
        return $matches[1] . ($complex ? $prefix : '') . ($ucFirst ? ucfirst($word) : $word);
    }

    public static function extractTableNameFromClassName($str) {
        $name = self::ucFirstToUnderscore($str);
        $name = self::pluralize($name);
        return $name;
    }

    public static function extractClassNameFromTableName($tabName, $namespace = '') {
        $pattern = '@_(?P<first>\w)@';
        if ($namespace) {
            $nsPart = self::ucFirstToUnderscore($namespace);
            if (strpos($tabName, $nsPart) === 0) {
                $tabName = substr($tabName, strlen($nsPart));
            }
        }
        $className = preg_replace_callback($pattern, function($matches) {
            return strtoupper($matches['first']);
        }, $tabName);
        return ucfirst(self::getSingular($className));

    }

    public static function ucFirstToUnderscore($str) {
        $name = '\\' . ltrim($str, '\\');
        $name = preg_replace_callback('@\\\\([A-Z])@', function($matches) {
            return '\\' . strtolower($matches[1]);
        }, $name);
        $name = preg_replace('@([A-Z])@', '_\\1', $name);
        $name = str_replace('\\', '_', strtolower(ltrim($name, '\\')));
        return $name;
    }


    private static $_oneLetEndings = ['o', 'x'];
    private static $_twoLetEndings = ['ch', 'sh', 'ss'];
    private static $_pluralizeExceptions = [
        'calf'  => 'calves',
        'half'  => 'halves',
        'knife' => 'knives',
        'leaf'  => 'leaves',
        'life'  => 'lives',
        'loaf'  => 'loaves',
        'self'  => 'selves',
        'sheaf' => 'sheaves',
        'shelf' => 'shelves',
        'thief' => 'thieves',
        'wife'  => 'wives',
        'wolf'  => 'wolves',
        'foot'  => 'feet',
        'tooth' => 'teeth',
        'man'   => 'men',
        'woman' => 'women',
        'mouse' => 'mice',
        'goose' => 'geese',
        'louse' => 'lice',
        'child' => 'children',
        'ox'    => 'oxen'
    ];

}

?>
