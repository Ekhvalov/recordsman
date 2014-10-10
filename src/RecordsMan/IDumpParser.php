<?php
namespace RecordsMan;

interface IDumpParser
{
    /**
     * Set SQL dump file as source
     *
     * @param string $filename
     */
    public function setSourceFile($filename);
    
    /**
     * Set SQL string as source
     *
     * @param string $sqlStr
     */
    public function setSourceStr($sqlStr);
    
    /**
     * Starts parsing. Callback will be called on every query.
     *
     * @param \Closure $callback Callback function, that recieves two params: (string $query, IDumpParser $parser)
     */
    public function parse(\Closure $callback);
    
    /**
     * Stops parsing process
     */
    public function stop();
}
