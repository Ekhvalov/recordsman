<?php
define('DS', DIRECTORY_SEPARATOR);
define('PROJECT_ROOT', dirname(__FILE__));
define('SRC',  PROJECT_ROOT . DS . 'src' . DS);
define('TESTS',  PROJECT_ROOT . DS . 'tests' . DS);

function autoLoad($className) {
    $file = SRC . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
}

spl_autoload_register('autoLoad');

require_once TESTS . 'test_items_setup.php';
require_once TESTS . 'DBConnected_TestCase.php';