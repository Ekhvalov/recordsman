<?php
namespace RecordsMan;

class SqlFileMigrator {

    protected $_dir     = '';
    protected $_tab     = 'migration';
    protected $_adapter = null;
    protected $_parser  = null;
    protected $_info    = [
        'queries' => 0,
        'error'   => '',
        'success' => false,
        'version' => 0
    ];

    public function __construct(IDBAdapter $adapter = null, IDumpParser $parser = null) {
        if ($adapter) {
            $this->setDbAdapter($adapter);
        }
        if ($parser) {
            $this->setParser($parser);
        }
        $this->_dir = getcwd();
    }

    /**
     * Set IDBAdapter instance
     *
     * @param IDBAdapter $adapter
     * @return SqlFileMigrator
     */
    public function setDbAdapter(IDBAdapter $adapter) {
        $this->_adapter = $adapter;
        return $this;
    }

    /**
     * Set IDumpParser instance
     *
     * @param IDumpParser $parser
     * @return SqlFileMigrator
     */
    public function setParser(IDumpParser $parser) {
        $this->_parser = $parser;
        return $this;
    }

    /**
     * Set current migration files dir
     *
     * @param string $dir
     * @return SqlFileMigrator
     */
    public function setSourceDir($dir) {
        $this->_dir = $dir;
        return $this;
    }

    /**
     * Set table, where migration data are stored
     *
     * @param string $tabName
     * @return SqlFileMigrator
     */
    public function setMigrationTab($tabName = 'migration') {
        $this->_tab = $tabName;
        return $this;
    }

    protected function _checkCurrentVersion() {
        $tables = $this->_adapter->getTables();
        if (!in_array($this->_tab, $tables)) {
            $sql = "CREATE TABLE `{$this->_tab}` (`version` INT NOT NULL DEFAULT '0')";
            $this->_adapter->query($sql);
        }
        $sql = "SELECT COUNT(*) FROM `{$this->_tab}`";
        if (!intval($this->_adapter->fetchSingleValue($sql))) {
            $sql = "INSERT INTO `{$this->_tab}` (`version`) VALUES (0)";
            $this->_adapter->query($sql);
        }
        $sql = "SELECT `version` FROM `{$this->_tab}` WHERE 1 LIMIT 1";
        return intval($this->_adapter->fetchSingleValue($sql));
    }

    protected function _saveVersion($version) {
        $sql = "UPDATE `{$this->_tab}` SET `version`='{$version}' WHERE 1 LIMIT 1";
        $this->_adapter->query($sql);
    }

    /**
     * Starts migration process
     *
     * @param int $version Migrate to version. For last version, pass false.
     * @param \Closure $logCallback Log function, that recieves two params: (string $query, array $info)
     * @return array Returns process info as array with keys: 'version','error','queries','success'
     */
    public function migrate($version = false, \Closure $logCallback = null) {
        $version = intval($version);
        $dir = new \DirectoryIterator($this->_dir);
        if (!$dir->isDir()) {
            //throw new \RuntimeException("{$this->_dir} is not a directory", 841);
        }
        if (!$this->_parser) {
            throw new \RuntimeException("Parser component is not set", 851);

        }
        if (!$this->_adapter) {
            throw new \RuntimeException("DbAdapter component is not set", 852);
        }
        $currentVersion = $this->_checkCurrentVersion();
        $info = &$this->_info;
        $db = $this->_adapter;
        $filePattern = '@^(?P<version>\d{2,4}).*$@';
        $info['version'] = $currentVersion;
        $info['success'] = true;
        $migrFiles = [];

        foreach ($dir as $entry) {
            if ($entry->isFile()) {
                if (!preg_match($filePattern, $entry->getFilename(), $matches)) {
                    continue;
                }
                $fileVer = intval(ltrim($matches['version'], '0'));
                if ($fileVer <= $currentVersion) {
                    continue;
                }
                $migrFiles[$fileVer] = $entry->getPathname();
            }
        }

        ksort($migrFiles);

        foreach ($migrFiles as $fileVer => $filePath) {
            $db->beginTransaction();
            $this->_parser->setSourceFile($filePath)->parse(function($query, $parser) use ($db, $logCallback, &$info) {
                try {
                    $db->query($query);
                    $info['queries']++;
                } catch (\Exception $e) {
                    $info['success'] = false;
                    $info['error'] = $e->getMessage();
                    $parser->stop();
                }
                if ($logCallback) {
                    $logCallback($query, $info);
                }
            });
            if (!$info['success']) {
                $db->rollBack();
                break;
            }
            $this->_saveVersion($fileVer);
            $info['version'] = $fileVer;
            $db->commit();
            if ($version && ($fileVer == $version)) {
                break;
            }
        }
        return $this->_info;
    }


}

?>
