<?php
namespace RecordsMan;


abstract class DBConnected_TestCase extends \PHPUnit_Framework_TestCase
{

    protected static $adapter;
    protected static $loader;

    public static function loadTestData() {
        $parser = new MysqlDumbParser();
        $parser->setSourceFile(__DIR__ . DIRECTORY_SEPARATOR . 'testing_items_dump.sql');
        $parser->parseAndExecute(self::$adapter);
        $parser->free();
        require_once __DIR__ . DIRECTORY_SEPARATOR . 'test_items_setup.php';
    }

    /**
     * Setting up DB connection and applying test dump
     */
    public static function setUpBeforeClass()
    {
        self::$adapter = new MySqlAdapter('127.0.0.1', 'root', '', 'recordsman');
        self::$loader = new Loader(self::$adapter);
        Record::setLoader(self::$loader);
        Record::getAdapter()->logging(true);
        self::loadTestData();
    }

    public static function tearDownAfterClass()
    {
        $parser = new MysqlDumbParser();
        $parser->setSourceFile(__DIR__ . DIRECTORY_SEPARATOR . 'testing_items_remove.sql');
        $parser->parseAndExecute(self::$adapter);
        $parser->free();
        self::$adapter->disconnect();
        self::$adapter = NULL;
    }

}
