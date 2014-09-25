<?php
namespace RecordsMan;

trait TExternalFields
{
    protected static $externalFields = [];

    public static function addExternalField($fieldName, $tableName, $fieldKey = null) {
        self::$externalFields[$fieldName]['table'] = $tableName;
        self::$externalFields[$fieldName]['fieldKey'] = $fieldKey ?: $fieldName;
        self::addProperty($fieldName, _createGetter(
            self::$externalFields[$fieldName]['table'],
            self::$externalFields[$fieldName]['fieldKey']
        ));

    }

}

function _createGetter($tableName, $fieldKey) {
    return function() use ($tableName, $fieldKey) {
        $sql = "SELECT `{$fieldKey}` FROM `{$tableName}` WHERE `parent_id`=?";
        /** @var Record $this */
        return Record::getAdapter()->fetchSingleValue($sql, [$this->id]);
    };
}