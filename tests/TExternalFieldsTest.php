<?php
namespace RecordsMan;
require_once __DIR__ . DIRECTORY_SEPARATOR . 'DBConnected_TestCase.php';
use Test\Item as Item;

class TExternalFieldsTest extends DBConnected_TestCase
{

    /**
     * @covers RecordsMan\TExternalFields::get
     */
    public function testGet()
    {
        $item1 = Item::load(1);
        $this->assertEquals('Item7 (top level)', $item1->title);
        $this->assertEquals('Full name of item#1', $item1->full_name);
        $this->assertEquals('150.55', $item1->price);
        $this->assertEquals('Коллективное бессознательное', $item1->full_text);
        $item2 = Item::load(2);
        $this->assertEquals('', $item2->full_name);
        $this->setExpectedException('RecordsMan\RecordsManException', '', 40);
        $item2->unexist_field;
    }

    /**
     * @covers RecordsMan\TExternalFields::set
     */
    public function testSet()
    {
        $item = Item::load(1);
        $item->full_name = 'New value';
        $this->assertEquals('New value', $item->full_name);
        $this->assertEquals('New value', $item->get("external.full_name"));
    }

    /**
     * @covers RecordsMan\TExternalFields::save
     */
    public function testSave()
    {
        $item = Item::load(1)->set([
            'title'     => 'Item new title',
            'full_name' => 'Item new name',
            'price'     => 1.11,
            'full_text' => 'New description'
        ])->save();
        $this->assertEquals('Item new title', $item->title);
        $this->assertEquals('Item new name', $item->full_name);
        $this->assertEquals('1.11', $item->price);
        $this->assertEquals('New description', $item->full_text);
        unset($item);
        $item = Item::load(1);
        $this->assertEquals('Item new title', $item->title);
        $this->assertEquals('Item new name', $item->full_name);
        $this->assertEquals('1.11', $item->price);
        $this->assertEquals('New description', $item->full_text);
        $newItem = Item::create()->set([
            'title'     => 'New item title',
            'full_name' => 'New item name',
            'full_text' => 'New item desc'
        ]);
        $newItem->price = 201.121;
        $id = $newItem->save()->id;
        unset($newItem);
        $newItem = Item::load($id);
        $this->assertEquals('New item title', $newItem->title);
        $this->assertEquals('New item name', $newItem->full_name);
        $this->assertEquals('201.121', $newItem->price);
        $this->assertEquals('New item desc', $newItem->full_text);
    }

    /**
     * @covers RecordsMan\TExternalFields::drop
     */
    public function testDrop()
    {
        $adapter = Record::getAdapter();
        $infoSql = "SELECT * FROM `test_items_info` WHERE `item_id`=1";
        $textSql = "SELECT * FROM `test_items_text` WHERE `item_id`=1";
        $infoRow = $adapter->fetchRow($infoSql);
        $textRow = $adapter->fetchRow($textSql);
        $this->assertNotEmpty($infoRow);
        $this->assertNotEmpty($textRow);
        Item::load(1)->drop();
        $infoRow = $adapter->fetchRow($infoSql);
        $textRow = $adapter->fetchRow($textSql);
        $this->assertEmpty($infoRow);
        $this->assertEmpty($textRow);
    }

}

?>
