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
        $this->assertCount(6, $tablesReflection->getValue());
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
        $this->assertNull($itemExt->sku);
        $this->assertNull($itemExt->length);
        $this->assertNull($itemExt->height);
        $this->assertNull($itemExt->width);
        $itemExt->setSku(1)->setLength(11.3)->setWidth(155)->setHeight(14)
            ->setCityName('Saint-Petersburg')
            ->setPopulation(4.9)
            ->saveExternalFields();
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->cityName);
        $this->assertEquals(4.9, $itemExt->cityPopulation);
        $this->assertEquals(1, $itemExt->sku);
        $this->assertEquals(11.3, $itemExt->length);
        $this->assertEquals(155, $itemExt->width);
        $this->assertEquals(14, $itemExt->height);
        /** @var TExternalFields|ItemExt $itemExt4 */
        $itemExt4 = ItemExt::load(4);
        $itemExt4->setSku(4)->setLength(11.3)->setWidth(155)->setHeight(14)->saveExternalFields();
        $itemExt4 = ItemExt::load(4);
        $this->assertEquals(4, $itemExt4->sku);
        $this->assertEquals(11.3, $itemExt4->length);
        $this->assertEquals(155, $itemExt4->width);
        $this->assertEquals(14, $itemExt4->height);
    }


}

