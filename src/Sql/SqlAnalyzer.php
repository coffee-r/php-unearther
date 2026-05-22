<?php

namespace CoffeeR\Unearther\Sql;

class SqlAnalyzer
{
    public function analyze($sql, array $binds = array(), array $caller = array(), $durationMs = null)
    {
        $normalized = $this->normalize($sql);

        return array(
            'operation' => $this->operation($normalized),
            'tables' => $this->tables($normalized),
            'duration_ms' => $durationMs,
            'statement_hash' => $this->statementHash($normalized),
            'bind_shape' => $this->bindShape($binds),
            'caller' => $caller,
        );
    }

    public function normalize($sql)
    {
        $sql = preg_replace('/--.*$/m', ' ', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql);
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return $sql;
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
        $patterns = array(
            '/\bfrom\s+([a-zA-Z0-9_.$]+)/i',
            '/\bjoin\s+([a-zA-Z0-9_.$]+)/i',
            '/\binto\s+([a-zA-Z0-9_.$]+)/i',
            '/\bupdate\s+([a-zA-Z0-9_.$]+)/i',
            '/\bdelete\s+from\s+([a-zA-Z0-9_.$]+)/i',
            '/\bmerge\s+into\s+([a-zA-Z0-9_.$]+)/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches)) {
                foreach ($matches[1] as $table) {
                    $table = strtoupper(trim($table, '"`[]'));
                    if (!in_array($table, $tables, true)) {
                        $tables[] = $table;
                    }
                }
            }
        }

        return $tables;
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
