<?php

namespace CoffeeR\Unearther\Adapter\CodeIgniter3;

use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Sql\SqlAnalyzer;

class ObservedDb
{
    private $db;
    private $collector;
    private $analyzer;

    public function __construct($db, Collector $collector, ?SqlAnalyzer $analyzer = null)
    {
        $this->db = $db;
        $this->collector = $collector;
        $this->analyzer = $analyzer ?: new SqlAnalyzer();
    }

    public function query($sql, $binds = false, $returnObject = null)
    {
        if ($returnObject === null) {
            $result = $this->db->query($sql, $binds);
        } else {
            $result = $this->db->query($sql, $binds, $returnObject);
        }

        $trace = $this->collector->current();
        if (!$trace || !$trace->isSampled()) {
            return $result;
        }

        $bindArray = is_array($binds) ? $binds : array();
        $this->collector->addSql($this->analyzer->analyze(
            $sql,
            $bindArray,
            $this->caller()
        ));

        return $result;
    }

    public function __call($name, array $arguments)
    {
        return call_user_func_array(array($this->db, $name), $arguments);
    }

    private function caller()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($trace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            if (strpos($frame['file'], 'ObservedDb.php') !== false) {
                continue;
            }

            return array(
                'file' => $frame['file'],
                'line' => isset($frame['line']) ? $frame['line'] : null,
                'class' => isset($frame['class']) ? $frame['class'] : null,
                'function' => isset($frame['function']) ? $frame['function'] : null,
            );
        }

        return array();
    }
}
