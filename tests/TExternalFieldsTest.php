<?php
namespace RecordsMan;

use Test\ItemExt;

class TExternalFieldsTest extends DBConnected_TestCase
{

    public function testAddExternalField()
    {
        $this->assertClassHasStaticAttribute('_externalFields', '\Test\ItemExt');
        $itemExtReflection = new \ReflectionClass('\Test\ItemExt');
        $tablesReflection = $itemExtReflection->getProperty('_externalFields');
        $tablesReflection->setAccessible(true);
        $this->assertTrue(is_array($tablesReflection->getValue()));
        $this->assertCount(0, $tablesReflection->getValue());
        ItemExt::init();
        $this->assertCount(2, $tablesReflection->getValue());
        $this->assertTrue(isset($tablesReflection->getValue()['cityName']));
        $this->assertEquals('item_city', $tablesReflection->getValue()['cityName']['table']);
        $this->assertEquals('title', $tablesReflection->getValue()['cityName']['fieldKey']);
    }

    public function testAddExternalField_Get()
    {
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->cityName);
        $this->assertEquals('4.5', $itemExt->cityPopulation);
        $itemExt = ItemExt::load(2);
        $this->assertEquals('Moscow', $itemExt->cityName);
        $this->assertEquals('11.5', $itemExt->cityPopulation);
    }

    public function testAddExternalField_Set()
    {
        /** @var TExternalFields|ItemExt $itemExt */
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->cityName);
        $this->assertEquals('4.5', $itemExt->cityPopulation);
        $itemExt->setCityName('Leningrad');
        $itemExt->setPopulation(4.8);
        $this->assertEquals('Leningrad', $itemExt->cityName);
        $this->assertEquals('4.8', $itemExt->cityPopulation);
    }

    public function testAddExternalField_Save()
    {
        /** @var TExternalFields|ItemExt $itemExt */
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->cityName);
        $this->assertEquals('4.5', $itemExt->cityPopulation);
        $itemExt->setCityName('Leningrad');
        $itemExt->setPopulation(4.8);
        $itemExt->saveExternalFields();
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Leningrad', $itemExt->cityName);
        $this->assertEquals('4.8', $itemExt->cityPopulation);
    }


}

