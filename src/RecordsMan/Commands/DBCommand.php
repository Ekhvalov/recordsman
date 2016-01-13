<?php
namespace RecordsMan\Commands;

use WP\App\Console\ActionCommand;
use WP\App\Console\ArgList;
use WP\App\Console\ArgDef;
use WP\App\Console\CommandArgs;
use RecordsMan\MySQLDumbParser;
use RecordsMan\SqlFileMigrator;
use RecordsMan\IDBAdapter;

/**
 * Manipulates with database schema.
 *
 * Basic usage: db {command} [args]
 *
 * Available commands:
 * migrate - perform schema migrations from all connected sources
 * drop    - clear all tables
 */
class DBCommand extends ActionCommand {

    private $_sources = [];

    /** @var IDBAdapter $_adapter */
    private $_adapter = null;


    public static function args() {
        return new ArgList([
            ArgDef::withName('version')
                ->desc('Migrate to specified version (default: 0 - migrate to last version)')
                ->defaultVal(0)
                ->type(ArgDef::INT),
            ArgDef::withName('source')->desc('Migrate only specified source')
        ]);
    }

    public static function requires() {
        return [IDBAdapter::class, 'db.migrate.sources'];
    }

    public function init($requirements) {
        $this->_sources = $requirements['db.migrate.sources'];
        $this->_adapter = $requirements[IDBAdapter::class];
    }

    public function mainAction(CommandArgs $_) {
        print("Use one of specified actions: migrate, drop\n");
    }

    public function migrateAction(CommandArgs $args) {
        if ($args['source']) {
            $src = $this->_sourceLookup($args['source']);
            return $this->_migrateSource($src['name'], $src['path'], $args['version']);
        }
        $status = 0;
        foreach ($this->_sources as $src) {
            $result = $this->_migrateSource($src['name'], $src['path']);
            if ($result != 0) {
                $status = $result;
            }
        }
        return $status;
    }

    public function dropAction(CommandArgs $_) {
        foreach ($this->_adapter->getTables() as $table) {
            $this->_adapter->query("DROP TABLE `{$table}`");
        }
        return 0;
    }

    private function _migrateSource($name, $path, $version = 0) {
        $tabName = "migrations_{$name}";
        $parser = new MySQLDumbParser();
        $migrator = new SqlFileMigrator($this->_adapter, $parser);
        $migrator->setSourceDir($path);
        $migrator->setMigrationTab($tabName);
        $info = $migrator->migrate($version ?: false);
        if ($info['success']) {
            print("Source {$name} is up to date. Current version: {$info['version']}.\n");
            print("Performed queries: {$info['queries']}.\n");
            return 0;
        }
        print("Error occurs while migrating source {$name}:\n");
        print("{$info['error']}\n");
        print('***************************************');
        print("Performed queries: {$info['queries']}.\n");
        print("Current version: {$info['version']}.\n");
        return 33;
    }

    private function _sourceLookup($name) {
        foreach ($this->_sources as $src) {
            if ($src['name'] == $name) {
                return $src;
            }
        }
        throw new \InvalidArgumentException("Source '{$name}' are not specified");
    }

}
