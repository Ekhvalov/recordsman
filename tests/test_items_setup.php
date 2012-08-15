<?php
namespace Test;

class Item extends \RecordsMan\Record {

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
    use \RecordsMan\TExternalFields;
    protected static $externalFields = [
        'test_items_info' => 'item_id',
        'test_items_text' => 'item_id'
    ];

}


class SubItem extends \RecordsMan\Record {

    protected static $tableName  = 'test_subitems';
    protected static $belongsTo  = ['\Test\Item' => 'item_id'];
    protected static $hasMany    = [
        '\Test\SubSubItem' => [
            'foreignKey' => 'subitem_id'
        ]
    ];
    //TODO: Ordering testing
    protected static $enumerable = 'num';

}


class SubSubItem extends \RecordsMan\Record {

    protected static $tableName  = 'test_subsubitems';
    protected static $belongsTo = [
        '\Test\SubItem' => 'subitem_id'
    ];

}

class RelatedItem extends \RecordsMan\Record {

    protected static $hasMany = [
        '\Test\ItemsRelation' => 'related_item_id'
    ];

}

class ItemsRelation extends \RecordsMan\Record {

    protected static $belongsTo = [
        '\Test\Item' => 'item_id',
        '\Test\RelatedItem' => 'related_item_id'
    ];

}

?>