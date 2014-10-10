<?php
namespace RecordsMan;

trait TPublicFields
{
    public function __set($field, $value) {
        if (!property_exists(__CLASS__, 'publicFields')) {
            $msg = 'Using of trait TPublicFields consider that static property $publicFields is declared';
            throw new \RuntimeException($msg);
        }
        if (!in_array($field, static::$publicFields)) {
            $class = get_class($this);
            throw new \RuntimeException("Field {$class}::{$field} was not declared as public", 41);
        }
        $this->set($field, $value);
    }
}
