<?php
namespace RecordsMan\Tests;
use RecordsMan\RecordSet;
use Test\Item;
use Test\SubItem;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'DBConnected_TestCase.php';

/**
 * Generated by PHPUnit_SkeletonGenerator on 2012-05-28 at 14:36:27.
 */
class RecordSetTest extends DBConnected_TestCase
{
    /**
     * @covers RecordsMan\RecordSet::create
     */
    public function testCreate()
    {
        $set = RecordSet::create('\Test\Item');
        $this->assertEquals(7, $set->count());
        $set = RecordSet::create('\Test\Item', 'id > 1');
        $this->assertEquals(6, $set->count());
        $set = RecordSet::create('\Test\Item', 'id > 1', ['id' => 'DESC'], 5);
        $this->assertEquals(5, $set->count());
        $this->assertEquals(7, $set[0]->id);
        $set = RecordSet::create('\Test\Item', ['id > 1', 'id < 10'], ['id' => 'DESC'], [5, 10]);
        $this->assertEquals(1, $set->count());
    }

    /**
     * @covers RecordsMan\RecordSet::createFromSql
     */
    public function testCreateFromSql()
    {
        $sql = "SELECT * FROM `test_items` WHERE `parent_id`=:pid AND `title` LIKE :title";
        $set = RecordSet::createFromSql('\Test\Item', $sql, ['pid' => 0, 'title' => 'item%']);
        $this->assertEquals(2, $set->count());
    }

    /**
     * @covers RecordsMan\RecordSet::createFromForeign
     */
    public function testCreateFromForeign()
    {
        $item = Item::load(1);
        $subitems = RecordSet::createFromForeign($item, '\Test\SubItem');
        $this->assertInstanceOf('\RecordsMan\RecordSet', $subitems);
        $this->assertEquals(2, $subitems->count());
        $this->setExpectedException('RecordsMan\RecordsManException', '', 50);
        RecordSet::createFromForeign($item, '\Test\SubSubItem');
    }

    /**
     * @covers RecordsMan\RecordSet::reload
     */
    public function testReload()
    {
        $set = RecordSet::create('\Test\Item');
        $title = $set[0]->title;
        $set[0]->title = 'Changed title';
        $this->assertEquals('Changed title', $set[0]->title);
        $set->reload();
        $this->assertEquals($title, $set[0]->title);
    }

    /**
     * @covers RecordsMan\RecordSet::add
     */
    public function testAdd()
    {
        /** @var Item $item */
        $item = Item::load(1);
        $subsCount = $item->subItems->count();
        $item->subItems->add(SubItem::create([
            'title' => 'New subitem'
        ]));
        $this->assertEquals($subsCount + 1, $item->subItems->count());
        /** @var SubItem $createdItem */
        $createdItem = SubItem::findFirst(null, ['id' => 'DESC']);
        $this->assertEquals($createdItem->item_id, $item->id);
        $this->assertEquals('New subitem', $createdItem->title);
        //TODO: Test counters updating, through relations, etc.
    }

    /**
     * @covers RecordsMan\RecordSet::filter
     */
    public function testFilter()
    {
        $set = RecordSet::create('\Test\Item');
        // Condition mode test
        $condition = ['parent_id = 0', 'title ~ item%'];
        $filteredSet = $set->filter($condition);
        $this->assertEquals(2, $filteredSet->count());
        foreach($filteredSet as $item) {
            $this->assertTrue($item->isMatch($condition));
            $this->assertEquals(0, $item->parent_id);
        }
        // Callback mode test
        $filteredSet = $set->filter(function() {
            /** @var \Test\Item $this */
            return $this->parent_id == 0;
        });
        $this->assertEquals(2, $filteredSet->count());
        foreach($filteredSet as $item) {
            $this->assertEquals(0, $item->parent_id);
        }
    }

    /**
     * @covers RecordsMan\RecordSet::isEmpty
     */
    public function testIsEmpty()
    {
        $set = RecordSet::create('\Test\Item', 'id > 1');
        $emptySet = RecordSet::create('\Test\Item', 'id > 10');
        $this->assertFalse($set->isEmpty());
        $this->assertTrue($emptySet->isEmpty());
    }

    /**
     * @covers RecordsMan\RecordSet::count
     */
    public function testCount()
    {
        // Tested everywhere
    }

}
