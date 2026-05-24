<?php

namespace CoffeeR\Unearth\Adapter\CodeIgniter3;

use CoffeeR\Unearth\Collector;
use CoffeeR\Unearth\Sql\SqlAnalyzer;

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
            $bindArray
        ));

        return $result;
    }

    public function __call($name, array $arguments)
    {
        return call_user_func_array(array($this->db, $name), $arguments);
    }

}
