<?php

namespace CoffeeR\Unearth\Report;

class MarkdownRenderer
{
    public function render(array $report)
    {
        $lines = array();
        $lines[] = '# Observed Behavior Evidence Report';
        $lines[] = '';
        $lines[] = '- Generated at: `' . $report['generated_at'] . '`';
        $lines[] = '- Observed entrypoints: `' . $this->entrypointCount($report) . '`';
        $lines[] = '- Traces: `' . (isset($report['trace_count']) ? $report['trace_count'] : '-') . '`';
        $lines[] = '- Observed window: `' . (isset($report['observed_started_at_min']) ? $report['observed_started_at_min'] : '-') . '` to `' . (isset($report['observed_started_at_max']) ? $report['observed_started_at_max'] : '-') . '`';
        $lines[] = '- Value mode: `' . (isset($report['value_mode']) ? $report['value_mode'] : 'normalized') . '`';
        $lines[] = '';
        $lines[] = '> Observed entrypoints are evidence views, not assumed migration units.';
        $lines[] = '';

        foreach ($this->entrypoints($report) as $entrypoint) {
            $lines[] = '## ' . $entrypoint['method'] . ' ' . $entrypoint['path'];
            $lines[] = '';
            $lines[] = '- Entrypoint key: `' . $this->backtickValue(isset($entrypoint['entrypoint_key']) ? $entrypoint['entrypoint_key'] : $entrypoint['method'] . ' ' . $entrypoint['path']) . '`';
            $lines[] = '- Entrypoint type: `' . $this->backtickValue(isset($entrypoint['entrypoint_type']) ? $entrypoint['entrypoint_type'] : 'http') . '`';
            $lines[] = '- Migration unit assumption: `' . $this->backtickValue(isset($entrypoint['migration_unit_assumption']) ? $entrypoint['migration_unit_assumption'] : 'not_assumed') . '`';
            $lines[] = '- Controller: `' . $this->backtickValue(isset($entrypoint['controller']) && $entrypoint['controller'] !== null ? $entrypoint['controller'] : '-') . '`';
            $lines[] = '- Action: `' . $this->backtickValue(isset($entrypoint['action']) && $entrypoint['action'] !== null ? $entrypoint['action'] : '-') . '`';
            $lines[] = '- Route: `' . $this->backtickValue(isset($entrypoint['route']) && $entrypoint['route'] !== null ? $entrypoint['route'] : '-') . '`';
            $lines[] = '- Controller path: `' . $this->backtickValue(isset($entrypoint['controller_path']) && $entrypoint['controller_path'] !== null ? $entrypoint['controller_path'] : '-') . '`';
            $lines[] = '- Observed requests: `' . $entrypoint['observed_count'] . '`';
            $lines[] = '- Error rate: `' . $this->percent(isset($entrypoint['error_rate']) ? $entrypoint['error_rate'] : 0.0) . '`';
            $lines[] = '';
            $lines[] = '### Status Codes';
            $lines[] = '';
            $lines[] = '| Status | Count |';
            $lines[] = '|---|---:|';
            foreach ($entrypoint['status_codes'] as $status => $count) {
                $lines[] = '| ' . $this->tableCell($status) . ' | ' . $this->tableCell($count) . ' |';
            }
            $lines[] = '';
            $lines[] = '### Errors';
            $lines[] = '';
            $lines[] = '| Error | Count | Representative Trace |';
            $lines[] = '|---|---:|---|';
            $errors = isset($entrypoint['errors']) && is_array($entrypoint['errors']) ? $entrypoint['errors'] : array();
            foreach ($errors as $error) {
                $lines[] = '| ' . $this->tableCell($error['error']) . ' | ' . $this->tableCell($error['count']) . ' | ' . $this->tableCell($error['representative_trace_id']) . ' |';
            }
            if (count($errors) === 0) {
                $lines[] = '| - | 0 | - |';
            }
            $lines[] = '';
            $lines[] = '### Request Shape';
            $lines[] = '';
            $lines = array_merge($lines, $this->shapeTable($entrypoint['request_shape']));
            $lines[] = '';
            $lines[] = '### Response Shape';
            $lines[] = '';
            $lines = array_merge($lines, $this->shapeTable($entrypoint['response_shape']));
            $lines[] = '';
            $lines[] = '### Tables';
            $lines[] = '';
            $lines = array_merge($lines, $this->tableCatalogTable(isset($entrypoint['table_catalog']) ? $entrypoint['table_catalog'] : array()));
            $lines[] = '';
            $lines[] = '### Observed Execution Patterns';
            $lines[] = '';
            $lines[] = '| Pattern | Count | Status | SQL Flow | Tables | External Calls |';
            $lines[] = '|---|---:|---|---|---|---|';
            foreach ($entrypoint['patterns'] as $pattern) {
                $lines[] = '| ' . $this->tableCell($this->patternId($pattern)) . ' | ' . $this->tableCell($pattern['count']) . ' | ' . $this->tableCell(implode(', ', $pattern['statuses'])) . ' | ' . $this->tableCell($this->sqlFlowLabel($pattern['sql_flow'])) . ' | ' . $this->tableCell(implode(', ', $pattern['tables'])) . ' | ' . $this->tableCell($this->externalLabel($pattern['external_http'])) . ' |';
            }
            $lines[] = '';

            foreach ($entrypoint['patterns'] as $pattern) {
                $lines[] = '### Behavior pattern: ' . $this->patternId($pattern);
                $lines[] = '';
                $lines[] = '- Observed: `' . $pattern['count'] . '`';
                $lines[] = '- Representative trace: `' . $pattern['representative_trace_id'] . '`';
                $lines[] = '';
                $lines[] = '#### SQL Statements';
                $lines[] = '';
                $lines[] = '| Step | Operation | Tables | Statement Hash | Statement (normalized) | Count |';
                $lines[] = '|---:|---|---|---|---|---:|';
                foreach ($pattern['sql_flow'] as $step) {
                    $lines[] = '| ' . $this->tableCell($step['step']) . ' | ' . $this->tableCell($step['operation']) . ' | ' . $this->tableCell(implode(', ', $step['tables'])) . ' | ' . $this->tableCell(isset($step['statement_hash']) ? $step['statement_hash'] : '') . ' | ' . $this->tableCell(isset($step['statement_normalized']) ? $step['statement_normalized'] : '') . ' | ' . $this->tableCell($step['count']) . ' |';
                }
                if (count($pattern['sql_flow']) === 0) {
                    $lines[] = '| - | - | - | - | - | 0 |';
                }
                $lines[] = '';
                $lines = array_merge($lines, $this->representativeSection($pattern));
                $lines[] = '';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function representativeSection(array $pattern)
    {
        $lines = array();
        $lines[] = '#### Representative Observed Flow';
        $lines[] = '';

        $rep = isset($pattern['representative_observed_flow']) && is_array($pattern['representative_observed_flow'])
            ? $pattern['representative_observed_flow']
            : (isset($pattern['representative']) && is_array($pattern['representative']) ? $pattern['representative'] : array());

        $lines[] = '- trace_id: `' . $this->backtickValue(isset($rep['trace_id']) ? $rep['trace_id'] : '-') . '`';
        $lines[] = '- status: `' . $this->backtickValue(isset($rep['status']) && $rep['status'] !== null ? $rep['status'] : '-') . '`';
        $lines[] = '- path (canonical): `' . $this->backtickValue(isset($rep['path_pattern']) && $rep['path_pattern'] !== null ? $rep['path_pattern'] : '-') . '`';
        $lines[] = '- path (concrete): `' . $this->backtickValue(isset($rep['path']) && $rep['path'] !== null ? $rep['path'] : '-') . '`';

        $queryRaw = isset($rep['query_raw']) ? $rep['query_raw'] : null;
        $queryShape = isset($rep['query_shape']) ? $rep['query_shape'] : array();
        if ($queryRaw !== null && $queryRaw !== '') {
            $lines[] = '- query: `' . $this->backtickValue($queryRaw) . '`';
        } elseif (is_array($queryShape) && count($queryShape) > 0) {
            $lines[] = '- query shape: `' . $this->backtickValue($this->encodeInline($queryShape)) . '`';
        } else {
            $lines[] = '- query: `{}` (none observed)';
        }

        $sql = isset($rep['sql']) && is_array($rep['sql']) ? $rep['sql'] : array();
        $lines[] = '- SQL count: `' . count($sql) . '`';
        foreach ($sql as $stmt) {
            $normalized = isset($stmt['statement_normalized']) ? (string) $stmt['statement_normalized'] : '';
            $tokenized = isset($stmt['statement_tokenized']) ? $stmt['statement_tokenized'] : null;
            $text = isset($stmt['statement_text']) ? $stmt['statement_text'] : null;
            $line = '  - `' . $this->backtickValue($normalized) . '`';
            if ($tokenized !== null && $tokenized !== '') {
                $line .= ' (tokenized: `' . $this->backtickValue($tokenized) . '`)';
            }
            if ($text !== null && $text !== '') {
                $line .= ' (concrete: `' . $this->backtickValue($text) . '`)';
            }
            $lines[] = $line;
        }

        $ext = isset($rep['external_http']) && is_array($rep['external_http']) ? $rep['external_http'] : array();
        if (count($ext) === 0) {
            $lines[] = '- external API calls: none';
        } else {
            $lines[] = '- external API calls:';
            foreach ($ext as $call) {
                $method = isset($call['method']) ? $call['method'] : '';
                $host = isset($call['host']) ? $call['host'] : '';
                $path = isset($call['path']) ? $call['path'] : '';
                $status = isset($call['status']) && $call['status'] !== null ? ' -> ' . $call['status'] : '';
                $lines[] = '  - `' . $this->backtickValue($method . ' ' . $host . $path . $status) . '`';
            }
        }

        return $lines;
    }

    private function entrypoints(array $report)
    {
        return isset($report['observed_entrypoints']) && is_array($report['observed_entrypoints']) ? $report['observed_entrypoints'] : array();
    }

    private function entrypointCount(array $report)
    {
        return isset($report['observed_entrypoint_count']) ? $report['observed_entrypoint_count'] : count($this->entrypoints($report));
    }

    private function patternId(array $pattern)
    {
        return isset($pattern['behavior_pattern_id']) ? $pattern['behavior_pattern_id'] : 'pattern';
    }

    private function encodeInline($value)
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function backtickValue($value)
    {
        $value = (string) $value;
        return str_replace('`', '\\`', $value);
    }

    private function shapeTable($shape)
    {
        $lines = array();
        $lines[] = '| Field | Type |';
        $lines[] = '|---|---|';
        foreach ($this->flattenShape($shape) as $field => $type) {
            $lines[] = '| ' . $this->tableCell($field) . ' | ' . $this->tableCell($type) . ' |';
        }
        if (count($lines) === 2) {
            $lines[] = '| - | - |';
        }

        return $lines;
    }

    private function flattenShape($shape, $prefix = '')
    {
        $flat = array();
        if (!is_array($shape)) {
            return $flat;
        }

        if ($this->isListShape($shape)) {
            $listPrefix = $prefix === '' ? '[]' : $prefix . '[]';
            foreach ($shape as $value) {
                if (is_array($value)) {
                    $flat = array_merge($flat, $this->flattenShape($value, $listPrefix));
                } else {
                    $flat[$listPrefix] = (string) $value;
                }
            }

            return $flat;
        }

        foreach ($shape as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenShape($value, $field));
            } else {
                $flat[$field] = (string) $value;
            }
        }

        return $flat;
    }

    private function isListShape(array $shape)
    {
        if (empty($shape)) {
            return false;
        }

        return array_keys($shape) === range(0, count($shape) - 1);
    }

    private function tableCatalogTable(array $catalog)
    {
        $lines = array();
        $lines[] = '| Table | Description |';
        $lines[] = '|---|---|';
        foreach ($catalog as $item) {
            $table = isset($item['table']) ? $item['table'] : '';
            $description = isset($item['description']) ? $item['description'] : '';
            $lines[] = '| ' . $this->tableCell($table) . ' | ' . $this->tableCell($description) . ' |';
        }
        if (count($catalog) === 0) {
            $lines[] = '| - | - |';
        }

        return $lines;
    }

    private function sqlFlowLabel(array $flow)
    {
        $labels = array();
        foreach ($flow as $step) {
            $tables = count($step['tables']) ? ' ' . implode('+', $step['tables']) : '';
            $labels[] = $step['operation'] . $tables;
        }

        return count($labels) ? implode(' -> ', $labels) : 'none';
    }

    private function externalLabel(array $items)
    {
        if (count($items) === 0) {
            return 'none';
        }

        $labels = array();
        foreach ($items as $item) {
            $labels[] = $item['method'] . ' ' . $item['host'] . $item['path'];
        }

        return implode(', ', $labels);
    }

    private function tableCell($value)
    {
        $value = (string) $value;
        $value = str_replace(array("\r\n", "\r", "\n"), '<br>', $value);
        $value = str_replace('|', '\\|', $value);
        $value = str_replace('`', '\\`', $value);

        return $value;
    }

    private function percent($rate)
    {
        return number_format(((float) $rate) * 100, 2, '.', '') . '%';
    }
}
