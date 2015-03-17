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

    /**
     * @param $sql
     * @param null $params
     * @return mixed
     * @throws RecordsManException
     */
    public function query($sql, $params = null) {
        if (!is_null($this->_masterConn)) {
            return $this->_masterConn->query($sql, $params);
        }
        throw new RecordsManException("MysqlMasterSlaveAdapter: can't write to slave");
    }

    /**
     * @return string
     */
    public function getLastInsertId() {
        if (!is_null($this->_masterConn)) {
            return $this->_masterConn->getLastInsertId();
        }
        return $this->_db->lastInsertId();
    }
}
