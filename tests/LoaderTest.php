<?php
namespace RecordsMan;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2012-05-24 at 20:33:20.
 */
class LoaderTest extends DBConnected_TestCase
{
    /** @var Loader $inst */
    protected $inst;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->inst = self::$loader;
    }

    /**
     * @covers RecordsMan\Loader::registerClass
     */
    public function testRegisterClass()
    {
        $loaderReflection = new \ReflectionClass($this->inst);
        $this->assertTrue($loaderReflection->hasProperty('_classes'));
        $_classes = $loaderReflection->getProperty('_classes');
        $this->assertTrue($_classes->isPrivate());
        $_classes->setAccessible(true);
        $this->assertTrue(is_array($_classes->getValue($this->inst)));
        $this->assertCount(0, $_classes->getValue($this->inst));
        $this->inst->registerClass('\Test\Item');
        $this->assertTrue(isset($_classes->getValue($this->inst)['\Test\Item']['properties']));
        $this->assertEquals('\Test\Item', $this->inst->registerClass('\Test\Item'));
        $this->assertEquals('\Test\Item', $this->inst->registerClass('Test\Item'));
    }

    /**
     * @covers RecordsMan\Loader::getFieldsDefinition
     */
    public function testGetFieldsDefinition()
    {
        $this->assertEquals(0, $this->inst->getFieldsDefinition('\Test\Item')['parent_id']);
    }

    /**
     * @covers RecordsMan\Loader::getClassRelationTypeWith
     */
    public function testGetClassRelationTypeWith()
    {
        $this->assertEquals(
            Record::RELATION_MANY,
            $this->inst->getClassRelationTypeWith('\Test\Item', '\Test\SubItem')
        );
        $this->assertEquals(
            Record::RELATION_BELONGS,
            $this->inst->getClassRelationTypeWith('\Test\SubItem', '\Test\Item')
        );
        $this->assertEquals(
            Record::RELATION_MANY,
            $this->inst->getClassRelationTypeWith('\Test\Item', '\Test\Item')
        );
    }

    /**
     * @covers RecordsMan\Loader::getClassRelationParamsWith
     */
    public function testGetClassRelationParamsWith()
    {
        $this->assertEquals(
            'item_id',
            $this->inst->getClassRelationParamsWith('\Test\Item', '\Test\SubItem')['foreignKey']
        );
        $this->assertEquals(
            'item_id',
            $this->inst->getClassRelationParamsWith('\Test\SubItem', '\Test\Item')['foreignKey']
        );
        $this->assertEquals(
            'parent_id',
            $this->inst->getClassRelationParamsWith('\Test\Item', '\Test\Item')['foreignKey']
        );
    }

    /**
     * @covers RecordsMan\Loader::getClassRelations
     */
    public function testGetClassRelations()
    {
        $this->assertContains(
            '\Test\Item',
            $this->inst->getClassRelations('\Test\Item')
        );
        $this->assertContains(
            '\Test\SubItem',
            $this->inst->getClassRelations('\Test\Item')
        );
        $this->assertContains(
            '\Test\Item',
            $this->inst->getClassRelations('\Test\SubItem', Record::RELATION_BELONGS)
        );
    }

    /**
     * @covers RecordsMan\Loader::getClassTableName
     */
    public function testGetClassTableName()
    {
        $this->assertEquals('test_items', $this->inst->getClassTableName('\Test\Item'));
    }

    /**
     * @covers RecordsMan\Loader::isTableExists
     */
    public function testIsTableExists()
    {
        $this->assertTrue($this->inst->isTableExists('test_items'));
        $this->assertFalse($this->inst->isTableExists('dumb_tab'));
    }

    /**
     * @covers RecordsMan\Loader::isFieldExists
     */
    public function testIsFieldExists()
    {
        $this->assertTrue($this->inst->isFieldExists('\Test\Item', 'parent_id'));
        $this->assertTrue($this->inst->isFieldExists('Test\Item', 'id'));
        $this->assertFalse($this->inst->isFieldExists('\Test\Item', 'dumb_field'));
        $this->assertFalse($this->inst->isFieldExists('Test\Item', 'dumb_field'));
    }

    /**
     * @covers RecordsMan\Loader::getClassCounters
     */
    public function testGetClassCounters()
    {
        $classesWithCounters = $this->inst->getClassCounters('\Test\SubItem');
        $this->assertEquals(1, count($classesWithCounters));
        $this->assertEquals('subitems_count', $classesWithCounters['\Test\Item']);
    }

    public function testAddClassProperty()
    {
        $loaderReflection = new \ReflectionClass($this->inst);
        $_classes = $loaderReflection->getProperty('_classes');
        $_classes->setAccessible(true);
        $this->assertFalse(isset($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['getters']));
        $this->assertFalse(isset($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['setters']));
        $this->inst->addClassProperty('\Test\Item', 'field', function() {});
        $this->assertTrue(isset($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['getters']));
        $this->assertTrue(isset($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['setters']));
        $this->assertTrue(is_array($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['getters']));
        $this->assertTrue(is_array($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['setters']));
        $this->assertCount(1, $_classes->getValue($this->inst)['\Test\Item']['properties']['field']['getters']);
        $this->assertCount(0, $_classes->getValue($this->inst)['\Test\Item']['properties']['field']['setters']);
        $this->inst->addClassProperty('\Test\Item', 'field', function() {}, function() {});
        $this->assertCount(2, $_classes->getValue($this->inst)['\Test\Item']['properties']['field']['getters']);
        $this->assertCount(1, $_classes->getValue($this->inst)['\Test\Item']['properties']['field']['setters']);
        foreach($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['getters'] as $callback) {
            $this->assertInstanceOf('\Closure', $callback);
        }
        foreach($_classes->getValue($this->inst)['\Test\Item']['properties']['field']['setters'] as $callback) {
            $this->assertInstanceOf('\Closure', $callback);
        }
    }

    public function testHasPropertyGetterCallbacks()
    {
        $this->assertFalse($this->inst->hasClassPropertyGetterCallbacks('\Test\Item', 'has_getter'));
        $this->inst->addClassProperty('\Test\Item', 'has_getter', function() {});
        $this->assertTrue($this->inst->hasClassPropertyGetterCallbacks('\Test\Item', 'has_getter'));
    }

    public function testGetClassPropertyGetterCallbacks()
    {
        $this->assertTrue(is_array($this->inst->getClassPropertyGetterCallbacks('\Test\Item', 'getter')));
        $this->assertCount(0, $this->inst->getClassPropertyGetterCallbacks('\Test\Item', 'getter'));
        $this->inst->addClassProperty('\Test\Item', 'getter', function() {});
        $this->assertCount(1, $this->inst->getClassPropertyGetterCallbacks('\Test\Item', 'getter'));
        $this->inst->addClassProperty('\Test\Item', 'getter', function() {});
        $this->assertCount(2, $this->inst->getClassPropertyGetterCallbacks('\Test\Item', 'getter'));
    }

    public function testGetClassPropertySetterCallbacks()
    {
        $this->assertTrue(is_array($this->inst->getClassPropertySetterCallbacks('\Test\Item', 'setter')));
        $this->assertCount(0, $this->inst->getClassPropertySetterCallbacks('\Test\Item', 'setter'));
        $this->inst->addClassProperty('\Test\Item', 'setter', function() {}, function() {});
        $this->assertCount(1, $this->inst->getClassPropertySetterCallbacks('\Test\Item', 'setter'));
        $this->inst->addClassProperty('\Test\Item', 'setter', function() {}, function() {});
        $this->assertCount(2, $this->inst->getClassPropertySetterCallbacks('\Test\Item', 'setter'));
    }

}
