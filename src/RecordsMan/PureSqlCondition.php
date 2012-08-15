<?php
namespace RecordsMan;

class PureSqlCondition extends Condition {

    private $_sql = '1';

    public static function create($operands, $boolOperator = Condition::OPERATOR_AND) {
        return new self($operands);
    }

    protected function __construct($sql) {
        $this->_sql = $sql;
    }

    public function compile() {
        return "({$this->_sql})";
    }

    public function test($item) {
        throw new RecordsManException("Can't test condition with PureSqlCondition block '{$this->_sql}''", 30);
    }

}
