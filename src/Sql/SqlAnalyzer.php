<?php

namespace CoffeeR\Unearther\Sql;

class SqlAnalyzer
{
    private $captureText;

    public function __construct($captureText = false)
    {
        $this->captureText = (bool) $captureText;
    }

    public function analyze($sql, array $binds = array(), array $caller = array(), $durationMs = null)
    {
        $rawSql = (string) $sql;
        $normalized = $this->normalize($rawSql);
        $fingerprint = $this->fingerprintNormalized($normalized);

        $event = array(
            'operation' => $this->operation($normalized),
            'tables' => $this->tables($normalized),
            'duration_ms' => $durationMs,
            'statement_hash' => $this->statementHash($fingerprint),
            'bind_shape' => $this->bindShape($binds),
            'caller' => $caller,
        );

        if ($this->captureText) {
            $event['raw_sql'] = $rawSql;
            $event['normalized_sql'] = $normalized;
            $event['fingerprint_sql'] = $fingerprint;
        }

        return $event;
    }

    public function normalize($sql)
    {
        $sql = preg_replace('/--.*$/m', ' ', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
    }

    public function fingerprint($sql)
    {
        return $this->fingerprintNormalized($this->normalize($sql));
    }

    public function statementHash($normalizedSql)
    {
        return 'sha256:' . hash('sha256', $normalizedSql);
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
            '/\bjoin\s+([a-zA-Z0-9_.$]+)/i',
            '/\binto\s+([a-zA-Z0-9_.$]+)/i',
            '/\bupdate\s+([a-zA-Z0-9_.$]+)/i',
            '/\bdelete\s+from\s+([a-zA-Z0-9_.$]+)/i',
            '/\bmerge\s+into\s+([a-zA-Z0-9_.$]+)/i',
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

    private function fingerprintNormalized($normalizedSql)
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", '?', $normalizedSql);
        $sql = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql);
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
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
}
