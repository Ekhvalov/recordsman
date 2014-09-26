<?php
namespace Test;

use \RecordsMan\Record;
use \RecordsMan\RecordSet;
use \RecordsMan\TExternalFields;

/**
 * @property int $id
 * @property int $parent_id
 * @property int $children_count
 * @property int $item_id
 * @property int $subitems_count
 * @property string $title
 * @property RecordSet $itemsRelations
 * @property RecordSet $relatedItems
 */
class Item extends Record {

    protected static $hasMany = [
        '\Test\Item' => [
            'foreignKey' => 'parent_id',
            'counter' => 'children_count'
        ],
        '\Test\SubItem' => [
            'foreignKey' => 'item_id',
            'counter' => 'subitems_count'
        ],
        '\Test\RelatedItem' => [
            //TODO: unique implementation testing
            'through' => '\Test\ItemsRelation'
        ],
        '\Test\ItemsRelation' => [
            'foreignKey' => 'item_id',
            'condition' => 'extra = one'
        ]
    ];
    protected static $belongsTo  = ['\Test\Item' => 'parent_id'];
    //TODO: TTree testing
    //TODO: Counters testing

    // External field testing
//    use TExternalFields;
//    protected static $externalFields = [
//        'test_items_info' => 'item_id',
//        'test_items_text' => 'item_id'
//    ];

    /**
     * @param $title
     * @return Item
     */
    public function setTitle($title) {
        return $this->set('title', $title);
    }

    public function setterTest($value) {
        return $this->set('setter_test', $value);
    }

}

/**
 * Class SubItem
 * @package Test
 * @property int $id
 * @property Item $item
 */
class SubItem extends Record {

    protected static $tableName  = 'test_subitems';
    protected static $belongsTo  = ['\Test\Item' => 'item_id'];
    protected static $hasMany    = [
        '\Test\SubSubItem' => [
            'foreignKey' => 'subitem_id'
        ]
    ];
    //TODO: Ordering testing
    protected static $enumerable = 'num';

    /**
     * @param $title
     * @return SubItem
     */
    public function setTitle($title) {
        return $this->set('title', $title);
    }

}

/**
 * Class SubSubItem
 * @package Test
 * @property int $id
 */
class SubSubItem extends Record {

    protected static $tableName  = 'test_subsubitems';
    protected static $belongsTo = [
        '\Test\SubItem' => 'subitem_id'
    ];

}

class RelatedItem extends Record {

    protected static $hasMany = [
        '\Test\ItemsRelation' => 'related_item_id'
    ];

}

class ItemsRelation extends Record {

    protected static $belongsTo = [
        '\Test\Item' => 'item_id',
        '\Test\RelatedItem' => 'related_item_id'
    ];

}

class ItemExt extends Record
{
    use TExternalFields;
    protected static $tableName = 'test_items';

    public static function init() {
        ItemExt::addExternalField('cityName', 'item_city', 'title');
        ItemExt::addExternalField('cityPopulation', 'item_city', 'population');
        ItemExt::addExternalField('sku', 'item_properties');
        ItemExt::addExternalField('length', 'item_properties');
        ItemExt::addExternalField('width', 'item_properties');
        ItemExt::addExternalField('height', 'item_properties');
    }

    /**
     * @param $name
     * @return ItemExt
     */
    public function setCityName($name) {
        return $this->set('cityName', $name);
    }

    /**
     * @param $population
     * @return ItemExt
     */
    public function setPopulation($population) {
        return $this->set('cityPopulation', $population);
    }

    /**
     * @param $sku
     * @return ItemExt
     */
    public function setSku($sku) {
        return $this->set('sku', $sku);
    }

    /**
     * @param $length
     * @return ItemExt
     */
    public function setLength($length) {
        return $this->set('length', $length);
    }

    /**
     * @param $height
     * @return ItemExt
     */
    public function setHeight($height) {
        return $this->set('height', $height);
    }

    /**
     * @param $width
     * @return ItemExt
     */
    public function setWidth($width) {
        return $this->set('width', $width);
    }
}
