<?php
namespace RecordsMan;

class ComparsionCondition extends Condition {

    private $_op1  = '';
    private $_op2  = '';
    private $_sign = '=';

    public static function create($operands, $boolOperator = Condition::OPERATOR_AND) {
        return self::_parseOperands($operands);
    }

    protected function __construct($op1, $op2, $sign) {
        $this->_op1 = $op1;
        $this->_op2 = $op2;
        $this->_sign = $sign;
    }

    public function compile() {
        $op2 = $this->_escape($this->_op2);
        switch($this->_sign) {
            case '!':
                return "(`{$this->_op1}`<>{$op2})";
            case '~':
                return "(`{$this->_op1}` LIKE {$op2})";
            default:
                return "(`{$this->_op1}`{$this->_sign}{$op2})";
        }
    }

    public function test($item) {
        $item = $this->_argToArray($item);
        if (!isset($item[$this->_op1])) {
            return false;
        }
        $itemValue = $item[$this->_op1];
        switch($this->_sign) {
            case '=':
                return ($itemValue == $this->_op2);
            case '!':
                return ($itemValue != $this->_op2);
            case '>':
                return ($itemValue > $this->_op2);
            case '<':
                return ($itemValue < $this->_op2);
            case '>=':
                return ($itemValue >= $this->_op2);
            case '<=':
                return ($itemValue <= $this->_op2);
            case '~':
                return $this->_testLikeCond($itemValue);
            default:
                return false;
        }
    }

    private function _escape($value) {
        if (is_numeric($value)) {
            return $value;
        }
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("'", "\'", $value);
        return "'{$value}'";
    }

    private function _testLikeCond($value) {
        $escapeChars = ['(', ')', '@', '[', ']', '^', '$', '*', '.', '?', '+'];
        $pattern = $this->_op2;
        foreach($escapeChars as $char) {
            $pattern = str_replace($char, "\\{$char}", $pattern);
        }
        $pattern = str_replace('%', '.*', $pattern);
        $pattern = "@^{$pattern}$@ui";
        return !!preg_match($pattern, $value);
    }

    private static function _parseOperands($conditionString) {
        $matches = [];
        $pattern = '/^(?<op1>\w+)\s*(?<sign>(>=)|(<=)|([=!><~]))\s*\'?(?<op2>.*?)\'?$/';
        if (preg_match($pattern, $conditionString, $matches)) {
            return new self($matches['op1'], $matches['op2'], $matches['sign']);
        }
        return PureSqlCondition::create($conditionString);
    }

}
