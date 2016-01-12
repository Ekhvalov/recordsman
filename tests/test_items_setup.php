<?php
namespace Test;

use \RecordsMan\Record;
use \RecordsMan\TExternalFields;

/**
 * @property int $id
 * @property int $parent_id
 * @property int $children_count
 * @property int $item_id
 * @property int $subitems_count
 * @property string $title
 * @property int $setter_test
 *
 * relations
 * @property-read \RecordsMan\RecordSet $items
 * @property-read \RecordsMan\RecordSet $subItems
 * @property-read \RecordsMan\RecordSet $relatedItems
 * @property-read \RecordsMan\RecordSet $itemsRelations
 */
class Item extends Record
{

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
 * @property-read int $item_id
 *
 * relations
 * @property-read \RecordsMan\RecordSet $subSubItems
 */
class SubItem extends Record
{

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
class SubSubItem extends Record
{

    protected static $tableName  = 'test_subsubitems';
    protected static $belongsTo = [
        '\Test\SubItem' => 'subitem_id'
    ];
}

/**
 * Class RelatedItem
 * @package Test
 *
 * relations
 * @property-read \RecordsMan\RecordSet $itemsRelations
 */
class RelatedItem extends Record
{

    protected static $hasMany = [
        '\Test\ItemsRelation' => 'related_item_id'
    ];
}

class ItemsRelation extends Record
{

    protected static $belongsTo = [
        '\Test\Item' => 'item_id',
        '\Test\RelatedItem' => 'related_item_id'
    ];
}

/**
 * Class ItemExt
 * @package Test
 * @property-read string $title
 * @property-read string $city_name
 * @property-read string $city_population
 * @property-read int $sku
 * @property-read float $length
 * @property-read float $width
 * @property-read float $height
 */
class ItemExt extends Record
{
    private static $baseInitCalls = 0;
    use TExternalFields;
    protected static $tableName = 'test_items';

    public static function init() {
        self::$baseInitCalls++;
        static::addExternalField('city_name', 'item_city');
        static::addExternalField('city_population', 'item_city');
        static::addExternalField('sku', 'item_properties');
        static::addExternalField('length', 'item_properties');
        static::addExternalField('width', 'item_properties');
        static::addExternalField('height', 'item_properties');
    }

    /**
     * @param $name
     * @return ItemExt
     */
    public function setCityName($name) {
        return $this->set('city_name', $name);
    }

    /**
     * @param $population
     * @return ItemExt
     */
    public function setPopulation($population) {
        return $this->set('city_population', $population);
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

/**
 * Class ItemBase
 * @package Test
 * @property-read string $title
 * @property-read string $width
 */
class ItemBase extends Record
{
    protected static $tableName = 'item_base';

    use TExternalFields;
    public static function init() {
        static::addExternalField('width', 'item_base_ext');
    }

    /**
     * @param $width
     * @return \Test\ItemBase
     * @throws \RecordsMan\RecordsManException
     */
    public function setWidth($width) {
        return $this->set('width', $width);
    }
}

/**
 * Class HeirItemExt
 * @package Test
 * @property-read string $height
 */
class ItemHeir extends ItemBase
{
    public static function init() {
        parent::init();
        static::addExternalField('height', 'item_heir_ext');
    }

    /**
     * @param $height
     * @return \Test\ItemHeir
     * @throws \RecordsMan\RecordsManException
     */
    public function setHeight($height) {
        return $this->set('height', $height);
    }
}

/**
 * Class Doc
 * @package Test
 *
 * @property-read string $type
 * @property-read string $title
 * @property-read int $id
 * @property-read string $description
 */
class Doc extends Record
{
    protected static $tableName = 'documents';

    use TExternalFields;

    public static function init() {
        self::addExternalField('description', 'doc_properties', [
            'doc_id' => true,
            'doc_type' => function (Doc $doc) {
                return $doc->type;
            }
        ]);
    }

    /**
     * @param string $newDescription
     * @return static|Doc|self
     * @throws \RecordsMan\RecordsManException
     */
    public function setDescription($newDescription) {
        return $this->set('description', $newDescription);
    }
}

/**
 * Class ExtendedDoc
 * @package Test
 *
 * @property-read string $meta
 */
class ExtendedDoc extends Doc
{
    public static function init() {
        parent::init();
        self::addExternalField('meta', 'doc_meta', [
            'doc_id' => true,
            'doc_type' => function (Doc $doc) {
                return $doc->type;
            }
        ]);
    }

    /**
     * @param string $meta
     * @return static|Doc|self
     * @throws \RecordsMan\RecordsManException
     */
    public function setMeta($meta) {
        return $this->set('meta', $meta);
    }
}
