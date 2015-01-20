<?php
namespace RecordsMan;


class MySQLMasterSlaveAdapter extends MySQLAdapter
{
    /**
     * @var IDBAdapter
     */
    private $_masterConn = null;

    /**
     * @param IDBAdapter $masterConn
     * @return MySQLMasterSlaveAdapter
     */
    public function setMasterConnection(IDBAdapter $masterConn) {
        $this->_masterConn = $masterConn;
        return $this;
    }

    public function query($sql, $params = null) {
        if (!is_null($this->_masterConn)) {
            return $this->_masterConn->query($sql, $params);
        }
        throw new RecordsManException("MysqlMasterSlaveAdapter: can't write to slave");
    }

}