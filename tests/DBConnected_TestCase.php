<?php
namespace RecordsMan\Tests;

use RecordsMan\Loader;
use RecordsMan\MySQLAdapter;
use RecordsMan\MySQLDumbParser;
use RecordsMan\Record;

abstract class DBConnected_TestCase extends \PHPUnit_Framework_TestCase
{
    /** @var MySqlAdapter $adapter */
    protected static $adapter;
    /** @var Loader $loader */
    protected static $loader;

    public static function loadTestData() {
        $parser = new MySQLDumbParser();
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
        self::$adapter = new MySQLAdapter('127.0.0.1', 'root', '', 'recordsman');
        self::$loader = new Loader(self::$adapter);
        Record::setLoader(self::$loader);
        self::$adapter->logging(true);
        self::loadTestData();
    }

    public static function tearDownAfterClass()
    {
        $parser = new MySQLDumbParser();
        $parser->setSourceFile(__DIR__ . DIRECTORY_SEPARATOR . 'testing_items_remove.sql');
        $parser->parseAndExecute(self::$adapter);
        $parser->free();
        self::$adapter->disconnect();
        self::$adapter = null;
    }
}
