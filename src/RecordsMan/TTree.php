<?php
namespace RecordsMan;

trait TTree
{
    private $_children = null;

    public function hasChildren() {
        $this->getChildren();
        return !empty($this->_children);
    }

    public function getChildren() {
        /** @var Record|TTree $this */
        if (is_null($this->_children)) {
            $this->_children = RecordSet::createFromForeign($this, get_class($this));
        }
        return $this->_children;
    }

    public function getParent() {

    }

    public static function init() {
        //echo get_called_class() . " inited (at TTree)<br/>";
    }
}
