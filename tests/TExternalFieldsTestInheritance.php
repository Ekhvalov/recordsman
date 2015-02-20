<?php
namespace RecordsMan;

use Test\ItemHeir;

class TExternalFieldsTestInheritance extends DBConnected_TestCase
{
    public function testAddExternalField_Create() {
        $this->assertNull(ItemHeir::findFirst('title=title 1'));
        /** @var ItemHeir $item */
        $item = ItemHeir::create(['title' => 'title 1']);
        $item->setWidth('base width 1');
        $item->setHeight('heir height 1');
        $item->save();
        $this->assertNotNull(ItemHeir::findFirst('title=title 1'));
    }

    /**
     * @depends testAddExternalField_Create
     */
    public function testAddExternalField_Get() {
        /** @var ItemHeir $item */
        $item = ItemHeir::load(1);
        $this->assertEquals('title 1', $item->title);
        $this->assertEquals('base width 1', $item->width);
        $this->assertEquals('heir height 1', $item->height);
    }

    /**
     * @depends testAddExternalField_Get
     */
    public function testAddExternalField_Set_Base() {
        /** @var ItemHeir $item */
        $item = ItemHeir::load(1);
        $this->assertEquals('base width 1', $item->width);
        $item->setWidth('new width')->save();
        $item = ItemHeir::load(1);
        $this->assertEquals('new width', $item->width);
    }

    /**
     * @depends testAddExternalField_Set_Base
     */
    public function testAddExternalField_Set_Heir() {
        /** @var ItemHeir $item */
        $item = ItemHeir::load(1);
        $this->assertEquals('heir height 1', $item->height);
        $item->setHeight('new height')->save();
        $item = ItemHeir::load(1);
        $this->assertEquals('new height', $item->height);
    }


    /**
     * @depends testAddExternalField_Get
     */
    public function testAddExternalField_Drop() {
        $this->assertNotNull(ItemHeir::load(1));
        $this->assertEquals(
            'new width',
            self::$adapter->fetchSingleValue("SELECT `width` FROM `item_base_ext` WHERE `item_base_id` = 1")
        );
        $this->assertEquals(
            'new height',
            self::$adapter->fetchSingleValue("SELECT `height` FROM `item_heir_ext` WHERE `item_base_id` = 1")
        );
        ItemHeir::load(1)->drop();
        $this->assertFalse(
            self::$adapter->fetchSingleValue("SELECT `width` FROM `item_base_ext` WHERE `item_base_id` = 1")
        );
        $this->assertFalse(
            self::$adapter->fetchSingleValue("SELECT `height` FROM `item_heir_ext` WHERE `item_base_id` = 1")
        );
        $this->setExpectedException('\RecordsMan\RecordsManException');
        ItemHeir::load(1);
    }
}
