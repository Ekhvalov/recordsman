<?php
namespace RecordsMan;


interface IRecordsCacher {

    public function getRecord($class, $id);

    public function getRecordSet($class, $key);

    public function storeRecord(Record $item, $lifetime = null);

    public function storeRecordSet(RecordSet $set, $key, $lifetime = null);

}
