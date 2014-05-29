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

    //TODO: '!~', '!:', ':' operators test
    public function compile() {
        if (($this->_sign != ':') && ($this->_sign != '!:')) {
            $op2 = $this->_escape($this->_op2);
        } else {
            $op2 = $this->_parseAndEscapeBlock($this->_op2);
        }
        $op1 = ($this->_prefix ? "{$this->_prefix}." : '') . "`{$this->_op1}`";
        switch($this->_sign) {
            case '!':
                return "({$op1}<>{$op2})";
            case '~':
                return "({$op1} LIKE {$op2})";
            case '!~':
                return "({$op1} NOT LIKE {$op2})";
            case ':':
                return "({$op1} IN (" . implode(',', $op2) . "))";
            case '!:':
                return "({$op1} NOT IN (" . implode(',', $op2) . "))";
            default:
                return "({$op1}{$this->_sign}{$op2})";
        }
    }

    //TODO: '!~' & ':' operators test
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
            case ':':
                return $this->_testInCond($itemValue);
            case '!:':
                return $this->_testNotInCond($itemValue);
            case '~':
                return $this->_testLikeCond($itemValue);
            case '!~':
                return $this->_testLikeCond($itemValue, true);
            default:
                return false;
        }
    }

    private function _trimQuotes($value) {
        $ret = trim($value);
        if (preg_match('/^\'(?P<value>.*)\'$/', $ret, $matches)) {
            $ret = $matches['value'];
        }
        return $ret;
    }

    private function _escape($value) {
        if (is_numeric($value)) {
            return $value;
        }
        $value = $this->_trimQuotes($value);
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("'", "\'", $value);
        return "'{$value}'";
    }

    private function _parseAndEscapeBlock($value) {
        return array_map(function($item) {
            return $this->_escape($this->_trimQuotes($item));
        }, explode(',', trim($value, ' []')));
    }

    private function _testLikeCond($value, $notLike = false) {
        $escapeChars = ['(', ')', '@', '[', ']', '^', '$', '*', '.', '?', '+'];
        $pattern = $this->_op2;
        foreach($escapeChars as $char) {
            $pattern = str_replace($char, "\\{$char}", $pattern);
        }
        $pattern = str_replace('%', '.*', $pattern);
        $pattern = "@^{$pattern}$@ui";
        return $notLike ? !preg_match($pattern, $value) : !!preg_match($pattern, $value);
    }

    private function _testInCond($value) {
        $checkValues = explode(',', trim($this->_op2, ' []'));
        foreach($checkValues as $check) {
            if ($value == $this->_trimQuotes($check)) {
                return true;
            }
        }
        return false;
    }

    private function _testNotInCond($value) {
        $checkValues = explode(',', trim($this->_op2, ' []'));
        foreach($checkValues as $check) {
            if ($value == $this->_trimQuotes($check)) {
                return false;
            }
        }
        return true;
    }

    private static function _parseOperands($conditionString) {
        $matches = [];
        $pattern = '/^(?<op1>\w+)\s*(?<sign>(>=)|(<=)|(!~)|(!:)|([:=!><~]))\s*(?<op2>\'?.*?\'?)$/';
        if (preg_match($pattern, $conditionString, $matches)) {
            return new self($matches['op1'], $matches['op2'], $matches['sign']);
        }
        throw new RecordsManException("Condition: can't parse given args");
        //return PureSqlCondition::create($conditionString);
    }

}
