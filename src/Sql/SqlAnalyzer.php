<?php

namespace CoffeeR\Unearther\Sql;

use CoffeeR\Unearther\Redaction\Redactor;

class SqlAnalyzer
{
    private $captureText;
    private $captureBindRaw;
    private $redactor;

    public function __construct($captureText = false, ?Redactor $redactor = null, $captureBindRaw = false)
    {
        $this->captureText = (bool) $captureText;
        $this->redactor = $redactor;
        $this->captureBindRaw = (bool) $captureBindRaw;
    }

    public function analyze($sql, array $binds = array(), array $caller = array())
    {
        $rawSql = (string) $sql;
        $statementNormalized = $this->normalizeWithPlaceholder($rawSql);
        $tables = $this->tables($statementNormalized);
        $operation = $this->operation($statementNormalized);

        return array(
            'operation' => $operation,
            'tables' => $tables,
            'statement_normalized' => $statementNormalized,
            'statement_tokenized' => $this->redactor ? $this->redactor->tokenizedSql($rawSql) : null,
            'statement_text' => $this->captureText ? $rawSql : null,
            'statement_hash' => $this->statementHash($statementNormalized),
            'bind_shape' => $this->bindShape($binds),
            'bind_tokens' => $this->redactor ? $this->redactor->tokens($binds) : null,
            'bind_raw' => $this->captureBindRaw ? $binds : null,
            'analysis' => $this->analysis($operation, $tables, $caller),
            'caller' => $caller,
        );
    }

    public function statementHash($statementNormalized)
    {
        return 'sha256:' . hash('sha256', $statementNormalized);
    }

    public function operation($sql)
    {
        if (preg_match('/^\s*([a-z]+)/i', $sql, $matches)) {
            return strtoupper($matches[1]);
        }

        return 'UNKNOWN';
    }

    public function tables($sql)
    {
        $tables = array();
        foreach ($this->fromTables($sql) as $table) {
            $this->addTable($tables, $table);
        }

        $patterns = array(
            '/\bjoin\s+([`"\[]?[a-zA-Z0-9_.$]+[`"\]]?)/i',
            '/\binto\s+([`"\[]?[a-zA-Z0-9_.$]+[`"\]]?)/i',
            '/\bupdate\s+([`"\[]?[a-zA-Z0-9_.$]+[`"\]]?)/i',
            '/\bdelete\s+from\s+([`"\[]?[a-zA-Z0-9_.$]+[`"\]]?)/i',
            '/\bmerge\s+into\s+([`"\[]?[a-zA-Z0-9_.$]+[`"\]]?)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches)) {
                foreach ($matches[1] as $table) {
                    $this->addTable($tables, $table);
                }
            }
        }

        return $tables;
    }

    private function normalizeWithPlaceholder($sql)
    {
        $sql = preg_replace('/--.*$/m', ' ', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
        $sql = preg_replace("/'(?:''|[^'])*'/", '{parameter}', $sql);
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '{parameter}', $sql);
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
    }

    private function fromTables($sql)
    {
        $tables = array();
        if (!preg_match_all('/\bfrom\s+(.+?)(?=\bwhere\b|\binner\s+join\b|\bleft\s+join\b|\bright\s+join\b|\bfull\s+join\b|\bcross\s+join\b|\bjoin\b|\bgroup\b|\border\b|\bhaving\b|\bconnect\b|\bstart\b|\bunion\b|$)/i', $sql, $matches)) {
            return $tables;
        }

        foreach ($matches[1] as $fromClause) {
            foreach (explode(',', $fromClause) as $part) {
                $part = trim($part);
                if ($part === '' || substr($part, 0, 1) === '(') {
                    continue;
                }
                if (preg_match('/^([`"\[]?[a-zA-Z0-9_.$]+[`"\]]?)/', $part, $tableMatches)) {
                    $tables[] = $tableMatches[1];
                }
            }
        }

        return $tables;
    }

    private function addTable(array &$tables, $table)
    {
        $table = strtoupper(trim((string) $table, '"`[]'));
        if ($table !== '' && !in_array($table, $tables, true)) {
            $tables[] = $table;
        }
    }

    private function bindShape(array $binds)
    {
        $shape = array();
        foreach ($binds as $key => $value) {
            if (is_null($value)) {
                $shape[$key] = 'null';
            } elseif (is_bool($value)) {
                $shape[$key] = 'boolean';
            } elseif (is_int($value) || is_float($value)) {
                $shape[$key] = 'number';
            } elseif (is_array($value)) {
                $shape[$key] = 'array';
            } else {
                $shape[$key] = 'string';
            }
        }

        return $shape;
    }

    private function analysis($operation, array $tables, array $caller)
    {
        $warnings = array();
        if (isset($caller['source']) && $caller['source'] === 'codeigniter3_query_history') {
            $warnings[] = 'query_history_capture_has_no_precise_caller_or_bind_values';
        }
        if (count($tables) === 0) {
            $warnings[] = 'tables_not_detected';
        }
        if ($operation === 'UNKNOWN') {
            $warnings[] = 'operation_not_detected';
        }

        return array(
            'analyzer' => 'regex',
            'operation_confidence' => $operation === 'UNKNOWN' ? 'unknown' : 'high',
            'tables_confidence' => count($tables) === 0 ? 'unknown' : 'best_effort',
            'warnings' => $warnings,
        );
    }
}
