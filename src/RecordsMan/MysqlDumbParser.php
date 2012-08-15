<?php
namespace RecordsMan;


class MysqlDumbParser implements IDumpParser {

    protected $_source     = null;
    protected $_sourceType = null;
    protected $_currLine   = 0;
    protected $_queryNum   = 0;
    protected $_fp         = null;
    protected $_stopped    = true;


    public function setSourceFile($filename) {
        $this->free();
        if (!is_readable($filename)) {
            throw new \RuntimeException("Can't read file {$filename}", 691);
        }
        $this->_source     = $filename;
        $this->_sourceType = 'file';
        return $this;
    }

    public function setSourceStr($sqlStr) {
        $this->free();
        $this->_source     = explode("\n", $sqlStr);
        $this->_sourceType = 'str';
        return $this;
    }

    protected function _getNextLine() {
        if ($this->_sourceType == 'file') {
            if (!is_resource($this->_fp)) {
                $this->_fp = fopen($this->_source, 'r');
            }
            if (feof($this->_fp)) {
                fclose($this->_fp);
                return false;
            }
            $this->_currLine++;
            return fgets($this->_fp);
        } elseif ($this->_sourceType == 'str') {
            if (isset($this->_source[$this->_currLine])) {
                $line = trim($this->_source[$this->_currLine]);
                $this->_currLine++;
                return $line;
            }
        }

        return false;
    }

    protected function _cutComments($str) {
        $state = 'skip'; // skip, in_dblquot, in_quot, in_comment
        $matches = [];
        $curMatch = [];
        $len = strlen($str);

        for($i=0; $i < $len; $i++) {
            switch ($str{$i}) {
                case '"':
                    if ($state == 'skip') {
                        $state = 'in_dblquot';
                    } elseif($state == 'in_dblquot') {
                        $state = 'skip';
                    }
                    break;
                case "'":
                    if ($state == 'skip') {
                        $state = 'in_quot';
                    } elseif($state == 'in_quot') {
                        $state = 'skip';
                    }
                    break;
                case '*':
                    if ( ($i > 0) && ($str{$i-1} == '/') ) {
                        if ($state == 'skip') {
                            $state = 'in_comment';
                            $curMatch['start'] = $i-1;
                        }
                    }
                    break;
                case '/':
                    if ( ($i > 0) && ($str{$i-1} == '*') ) {
                        if ($state == 'in_comment') {
                            $state = 'skip';
                            $curMatch['end'] = $i;
                            $matches[] = $curMatch;
                            $curMatch = [];
                        }
                    }
                    break;
            }
        }
        $deleted = 0;
        foreach($matches as $match) {
            if (isset($match['start']) && isset($match['end'])) {
                $length = $match['end'] - $match['start'] + 1;
                $str = substr_replace($str, '', ($match['start'] - $deleted), $length);
                $deleted += $length;
            }
        }
        return $str;
    }

    public function parseAndExecute(IDBAdapter $adapter) {
        $this->parse(function ($query) use ($adapter) {
            $adapter->query($query);
        });
        return $this->_queryNum;
    }

    public function parse(\Closure $callback) {
        if (!$this->_source) {
            throw new \RuntimeException("SQL source is not set", 692);
        }
        $query = '';
        $state = 'skip'; // skip, in_quot, in_dblquot
        $queryStarted = false;

        $inLinePtrn = '@--.*$@';
        $comPtrn = '@/\*.*\*/@';

        $this->_stopped = false;

        while (($line = $this->_getNextLine()) !== false) {
            if ($state == 'skip') {
                $line = preg_replace($inLinePtrn, '', $line);
                $line = preg_replace($comPtrn, '', $line);
            }
            if (strlen($line) == 0) {
                continue ;
            }
            if ((substr_count($line, '"') % 2) == 1) {
                if ($state == 'in_dblquot') {
                    $state = 'skip';
                } elseif ($state == 'skip') {
                    $state = 'in_dblquot';
                }
            }
            if ((substr_count($line, "'") % 2) == 1) {
                if ($state == 'in_quot') {
                    $state = 'skip';
                } elseif ($state == 'skip') {
                    $state = 'in_quot';
                }
            }
            if (!$queryStarted) {
                $queryStarted = true;
            }

            if (($comma = strpos($line, ';')) !== false) {
                $query.= substr($line, 0, $comma);
                $query = $this->_cutComments($query);
                $query = trim($query);
                if ($query) {
                    $this->_queryNum++;
                    $callback($query, $this);
                    if ($this->_stopped) {
                        break;
                    }
                }
                $queryStarted = false;
                $query = '';
            } else {
                $query.= $line;
            }

        }

        return $this;
    }

    public function stop() {
        $this->_stopped = true;
        $this->free();
        return $this;
    }

    public function free() {
        if (is_resource($this->_fp)) {
            fclose($this->_fp);
        }
        $this->_fp         = null;
        $this->_currLine   = 0;
        $this->_source     = '';
        $this->_sourceType = null;
        $this->_currLine   = 0;
        return $this;
    }


}

?>
