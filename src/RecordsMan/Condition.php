<?php
namespace RecordsMan;

class Condition {

    const OPERATOR_OR  = 'OR';
    const OPERATOR_AND = 'AND';

    protected $_compiled = null;

    public static function create($operands, $boolOperator = self::OPERATOR_AND) {
        if ($operands instanceof Condition) {
            return $operands;
        }
        if (is_array($operands)) {
            return BoolCondition::create($operands, $boolOperator);
        }
        return ComparsionCondition::create($operands);
    }

    public static function createAndBlock($operands) {
        return self::create($operands, self::OPERATOR_AND);
    }

    public static function createOrBlock($operands) {
        return self::create($operands, self::OPERATOR_OR);
    }

    final public function toSql() {
        if (is_null($this->_compiled)) {
            $this->_compiled = $this->compile();
        }
        return $this->_compiled;
    }

    public function __toString() {
        return $this->toSql();
    }

    public function compile() {
        return '1';
    }

    public function test($item) {
        return false;
    }

    protected function __construct() {

    }

    protected function _argToArray($arg) {
        if ($arg instanceof Record) {
            return $arg->toArray();
        }
        if (!is_array($arg)) {
            throw new \InvalidArgumentException("Argument must be an array", 20);
        }
        return $arg;
    }

    protected function _argToCondition($arg) {
        if (!($arg instanceof Condition)) {
            $arg = Condition::create($arg);
        }
        return $arg;
    }
}
