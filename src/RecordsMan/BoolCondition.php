<?php
namespace RecordsMan;

class BoolCondition extends Condition {

    private $_operands = [];
    private $_operator = 'AND';

    public static function create($operands, $boolOperator = Condition::OPERATOR_AND) {
        if (!is_array($operands)) {
            throw new \InvalidArgumentException("First argument must be an array", 20);
        }
        return new self($operands, $boolOperator);
    }

    protected function __construct($operands, $operator) {
        $this->_operands = $operands;
        $this->_operator = ($operator == Condition::OPERATOR_OR) ? Condition::OPERATOR_OR : Condition::OPERATOR_AND;
    }

    public function compile() {
        $compiled = '(';
        $first = true;
        foreach($this->_operands as $op) {
            $cond = $this->_argToCondition($op);
            $compiled .= (!$first ? (' ' . $this->_operator . ' ') : '') . $cond->toSql();
            $first = false;
        }
        return "{$compiled})";
    }

    public function test($item) {
        $item = $this->_argToArray($item);
        return ($this->_operator == Condition::OPERATOR_OR) ? $this->_testOr($item) : $this->_testAnd($item);
    }

    private function _testAnd($item) {
        foreach($this->_operands as $op) {
            $cond = $this->_argToCondition($op);
            if (!$cond->test($item)) {
                return false;
            }
        }
        return true;
    }

    private function _testOr($item) {
        foreach($this->_operands as $op) {
            $cond = $this->_argToCondition($op);
            if ($cond->test($item)) {
                return true;
            }
        }
        return false;
    }

}
