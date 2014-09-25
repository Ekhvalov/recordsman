<?php
define('DS', DIRECTORY_SEPARATOR);
define('PROJECT_ROOT', dirname(__FILE__));
define('SRC',  PROJECT_ROOT . DS . 'src' . DS);
define('TESTS',  PROJECT_ROOT . DS . 'tests' . DS);

function getPaths($className) {
    return [
        TESTS .  array_pop(explode('\\', $className)) . '.php',
        SRC . str_replace('\\', DS, $className) . '.php'
    ];

}

function autoLoad($className) {
    foreach(getPaths($className) as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
}

spl_autoload_register('autoLoad');

require_once TESTS . 'test_items_setup.php';