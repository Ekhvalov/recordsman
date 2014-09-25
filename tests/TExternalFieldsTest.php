<?php
namespace RecordsMan;

use Test\ItemExt;

class TExternalFieldsTest extends DBConnected_TestCase
{

    public function testAddExternalField()
    {
        $this->assertClassHasStaticAttribute('externalFields', '\Test\ItemExt');
        $itemExtReflection = new \ReflectionClass('\Test\ItemExt');
        $tablesReflection = $itemExtReflection->getProperty('externalFields');
        $tablesReflection->setAccessible(true);
        $this->assertTrue(is_array($tablesReflection->getValue()));
        $this->assertCount(0, $tablesReflection->getValue());
        ItemExt::addExternalField('cityName', 'item_city');
        $this->assertCount(1, $tablesReflection->getValue());
        $this->assertTrue(isset($tablesReflection->getValue()['cityName']));
        $this->assertEquals('item_city', $tablesReflection->getValue()['cityName']['table']);
        $this->assertEquals('cityName', $tablesReflection->getValue()['cityName']['fieldKey']);
        ItemExt::addExternalField('cityName', 'item_city', 'title');
        $this->assertEquals('item_city', $tablesReflection->getValue()['cityName']['table']);
        $this->assertEquals('title', $tablesReflection->getValue()['cityName']['fieldKey']);
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->cityName);
    }

}

