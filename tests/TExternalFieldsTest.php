<?php
namespace RecordsMan;

use Test\ItemExt;

class TExternalFieldsTest extends DBConnected_TestCase
{
    public function testAddExternalField() {
        $this->assertClassHasStaticAttribute('_fieldTable', '\Test\ItemExt');
        $this->assertClassHasStaticAttribute('_tableForeignKey', '\Test\ItemExt');
        $itemExtReflection = new \ReflectionClass('\Test\ItemExt');
        $fieldsReflection = $itemExtReflection->getProperty('_fieldTable');
        $fieldsReflection->setAccessible(true);
        $this->assertTrue(is_array($fieldsReflection->getValue()));
        $this->assertCount(0, $fieldsReflection->getValue());
        $tablesReflection = $itemExtReflection->getProperty('_tableForeignKey');
        $tablesReflection->setAccessible(true);
        $this->assertTrue(is_array($tablesReflection->getValue()));
        $this->assertCount(0, $tablesReflection->getValue());
        ItemExt::init();
        $this->assertCount(6, $fieldsReflection->getValue());
        $this->assertTrue(isset($fieldsReflection->getValue()['city_name']));
        $this->assertTrue(isset($fieldsReflection->getValue()['city_population']));
        $this->assertTrue(isset($fieldsReflection->getValue()['sku']));
        $this->assertTrue(isset($fieldsReflection->getValue()['length']));
        $this->assertTrue(isset($fieldsReflection->getValue()['width']));
        $this->assertTrue(isset($fieldsReflection->getValue()['height']));
        $this->assertCount(2, $tablesReflection->getValue());
        $this->assertTrue(isset($tablesReflection->getValue()['item_city']));
        $this->assertTrue(isset($tablesReflection->getValue()['item_properties']));
    }

    public function testAddExternalField_Get() {
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->city_name);
        $this->assertEquals('4.5', $itemExt->city_population);
        $itemExt = ItemExt::load(2);
        $this->assertEquals('Moscow', $itemExt->city_name);
        $this->assertEquals('11.5', $itemExt->city_population);
    }

    public function testAddExternalField_Set() {
        /** @var TExternalFields|ItemExt $itemExt */
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->city_name);
        $this->assertEquals('4.5', $itemExt->city_population);
        $itemExt->setCityName('Leningrad');
        $itemExt->setPopulation(4.8);
        $this->assertEquals('Leningrad', $itemExt->city_name);
        $this->assertEquals('4.8', $itemExt->city_population);
    }

    public function testAddExternalField_Save() {
        /** @var TExternalFields|ItemExt $itemNew */
        $itemNew = ItemExt::create(['title' => 'Item8']);
        $itemNew->setCityName('Novgorod');
        $itemNew->setPopulation(2);
        $itemNew->setSku(3)
            ->setHeight(15.5)
            ->setLength(100.1)
            ->setWidth(700.2)
            ->save();
        $itemNew = ItemExt::findFirst('title=Item8');
        $this->assertNotNull($itemNew);
        $this->assertEquals('Novgorod', $itemNew->city_name);
        $this->assertEquals(2, $itemNew->city_population);
        $this->assertEquals(3, $itemNew->sku);
        $this->assertEquals(15.5, $itemNew->height);
        $this->assertEquals(100.1, $itemNew->length);
        $this->assertEquals(700.2, $itemNew->width);
        /** @var TExternalFields|ItemExt $itemExt */
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->city_name);
        $this->assertEquals('4.5', $itemExt->city_population);
        $itemExt->setCityName('Leningrad');
        $itemExt->setPopulation(4.8);
        $itemExt->save();
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Leningrad', $itemExt->city_name);
        $this->assertEquals('4.8', $itemExt->city_population);
        $this->assertNull($itemExt->sku);
        $this->assertNull($itemExt->length);
        $this->assertNull($itemExt->height);
        $this->assertNull($itemExt->width);
        $itemExt->setSku(1)->setLength(11.3)->setWidth(155)->setHeight(14)
            ->setCityName('Saint-Petersburg')
            ->setPopulation(4.9)
            ->save();
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->city_name);
        $this->assertEquals(4.9, $itemExt->city_population);
        $this->assertEquals(1, $itemExt->sku);
        $this->assertEquals(11.3, $itemExt->length);
        $this->assertEquals(155, $itemExt->width);
        $this->assertEquals(14, $itemExt->height);
        /** @var TExternalFields|ItemExt $itemExt4 */
        $itemExt4 = ItemExt::load(4);
        $itemExt4->setSku(4)->setLength(11.3)->setWidth(155)->setHeight(14)->save();
        $itemExt4 = ItemExt::load(4);
        $this->assertEquals(4, $itemExt4->sku);
        $this->assertEquals(11.3, $itemExt4->length);
        $this->assertEquals(155, $itemExt4->width);
        $this->assertEquals(14, $itemExt4->height);
    }

    public function testAddExternalField_Drop() {
        /** @var TExternalFields|ItemExt $itemExt */
        $itemExt = ItemExt::load(1);
        $this->assertEquals('Saint-Petersburg', $itemExt->city_name);
        $this->assertEquals(4.9, $itemExt->city_population);
        $this->assertEquals(1, $itemExt->sku);
        $this->assertEquals(11.3, $itemExt->length);
        $this->assertEquals(155, $itemExt->width);
        $this->assertEquals(14, $itemExt->height);
        $itemExt->callTrigger(Record::DELETED);
        $itemExt = ItemExt::load(1);
        $this->assertNull($itemExt->city_name);
        $this->assertNull($itemExt->city_population);
        $this->assertNull($itemExt->sku);
        $this->assertNull($itemExt->length);
        $this->assertNull($itemExt->height);
        $this->assertNull($itemExt->width);
    }
}
