<?php
namespace RecordsMan\Tests;
use RecordsMan\Helper;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2012-05-24 at 20:33:20.
 */
class HelperTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @covers RecordsMan\Helper::qualifyClassName
     */
    public function testQualifyClassName()
    {
        $this->assertEquals('\Test\Item', Helper::qualifyClassName('\Test\Item'));
        $this->assertEquals('\Test\Item', Helper::qualifyClassName('Test\Item'));
        $this->assertEquals('Item', Helper::qualifyClassName('Item'));
    }

    /**
     * @covers RecordsMan\Helper::getClassNamespace
     */
    public function testGetClassNamespace()
    {
        $this->assertEquals('\Test\\', Helper::getClassNamespace('\Test\Item'));
        $this->assertEquals('', Helper::getClassNamespace('\Item'));
        $this->assertEquals('', Helper::getClassNamespace('Item'));
    }

    /**
     * @covers RecordsMan\Helper::extractClassName
     */
    public function testExtractClassName()
    {
        $this->assertEquals('Item', Helper::extractClassName('\Test\Item'));
        $this->assertEquals('Item', Helper::extractClassName('Item'));
    }

    /**
     * @covers RecordsMan\Helper::createSelectQuery
     */
    public function testCreateSelectQuery()
    {
        $tabName = 'test_items';
        $condition = ['id > 1', 'title ~ test%'];
        $this->assertEquals(
            "SELECT * FROM `{$tabName}` WHERE ((`id`>1) AND (`title` LIKE 'test%'))",
            Helper::createSelectQuery($tabName, $condition)
        );
        $this->assertEquals(
            "SELECT * FROM `{$tabName}` WHERE ((`id`>1) AND (`title` LIKE 'test%')) ORDER BY `id` DESC",
            Helper::createSelectQuery($tabName, $condition, ['id' => 'DESC'])
        );
        $this->assertEquals(
            "SELECT * FROM `{$tabName}` WHERE ((`id`>1) AND (`title` LIKE 'test%')) ORDER BY `id` LIMIT 0,10",
            Helper::createSelectQuery($tabName, $condition, 'id', [0, 10])
        );
    }

    /**
     * @covers RecordsMan\Helper::createSelectJoinQuery
     */
    public function testCreateSelectJoinQuery()
    {
        $tabName = 'test_related_items';
        $joinedTab = 'test_items_relations';
        $foreignKey = 'related_item_id';
        $condition = ['item_id=1'];
        $this->assertEquals(
            "SELECT a.* FROM `{$tabName}` AS a JOIN `{$joinedTab}` AS b ON a.`id`=b.`{$foreignKey}` WHERE ((`item_id`=1))",
            Helper::createSelectJoinQuery($tabName, $joinedTab, $foreignKey, $condition)
        );
    }

    /**
     * @covers RecordsMan\Helper::orderToSql
     */
    public function testOrderToSql()
    {
        $this->assertEquals(
            '`id`',
            Helper::orderToSql('id')
        );
        $this->assertEquals(
            '`id` DESC, `title` ASC',
            Helper::orderToSql(['id' => 'desc', 'title' => 'asc'])
        );
    }

    /**
     * @covers RecordsMan\Helper::limitToSql
     */
    public function testLimitToSql()
    {
        $this->assertEquals(
            '10',
            Helper::limitToSql(10)
        );
        $this->assertEquals(
            '20,10',
            Helper::limitToSql([20, 10])
        );
    }

    /**
     * @covers RecordsMan\Helper::getSingular
     */
    public function testGetSingular()
    {
        $this->assertEquals('Wolf', Helper::getSingular('Wolves'));
        $this->assertEquals('Point', Helper::getSingular('Points'));
        $this->assertEquals('\Test\Item', Helper::getSingular('\Test\Items'));
        $this->assertEquals('\Test\SubItem', Helper::getSingular('\Test\SubItems'));
    }

    /**
     * @covers RecordsMan\Helper::pluralize
     */
    public function testPluralize()
    {
        $this->assertEquals('Children', Helper::pluralize('Child'));
        $this->assertEquals('Points', Helper::pluralize('Point'));
        $this->assertEquals('\Test\Items', Helper::pluralize('\Test\Item'));
        $this->assertEquals('\Test\SubItems', Helper::pluralize('\Test\SubItem'));
    }

    /**
     * @covers RecordsMan\Helper::extractTableNameFromClassName
     */
    public function testExtractTableNameFromClassName()
    {
        $this->assertEquals('mod_wolves', Helper::extractTableNameFromClassName('\Mod\Wolf'));
        $this->assertEquals('mod_points', Helper::extractTableNameFromClassName('\Mod\Point'));
        $this->assertEquals('items', Helper::extractTableNameFromClassName('Item'));
    }

    /**
     * @covers RecordsMan\Helper::extractClassNameFromTableName
     */
    public function testExtractClassNameFromTableName()
    {
        $this->assertEquals('ModWolf', Helper::extractClassNameFromTableName('mod_wolves'));
        $this->assertEquals('Wolf', Helper::extractClassNameFromTableName('mod_wolves', 'Mod'));
        $this->assertEquals('Item', Helper::extractClassNameFromTableName('test_items', '\Test'));
    }
}
