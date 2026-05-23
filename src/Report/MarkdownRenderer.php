<?php

namespace CoffeeR\Unearther\Report;

class MarkdownRenderer
{
    public function render(array $report)
    {
        $lines = array();
        $lines[] = '# Observed API Behavior Report';
        $lines[] = '';
        $lines[] = '- Generated at: `' . $report['generated_at'] . '`';
        $lines[] = '- Endpoints: `' . $report['endpoint_count'] . '`';
        $lines[] = '';

        foreach ($report['endpoints'] as $endpoint) {
            $lines[] = '## ' . $endpoint['method'] . ' ' . $endpoint['path'];
            $lines[] = '';
            $lines[] = '- Observed requests: `' . $endpoint['observed_count'] . '`';
            $lines[] = '- Avg duration: `' . $endpoint['avg_duration_ms'] . 'ms`';
            $lines[] = '- P95 duration: `' . $endpoint['p95_duration_ms'] . 'ms`';
            $lines[] = '- Max duration: `' . $endpoint['max_duration_ms'] . 'ms`';
            $lines[] = '';
            $lines[] = '### Status Codes';
            $lines[] = '';
            $lines[] = '| Status | Count |';
            $lines[] = '|---|---:|';
            foreach ($endpoint['status_codes'] as $status => $count) {
                $lines[] = '| ' . $this->tableCell($status) . ' | ' . $this->tableCell($count) . ' |';
            }
            $lines[] = '';
            $lines[] = '### Request Shape';
            $lines[] = '';
            $lines = array_merge($lines, $this->shapeTable($endpoint['request_shape']));
            $lines[] = '';
            $lines[] = '### Response Shape';
            $lines[] = '';
            $lines = array_merge($lines, $this->shapeTable($endpoint['response_shape']));
            $lines[] = '';
            $lines[] = '### Observed Execution Patterns';
            $lines[] = '';
            $lines[] = '| Pattern | Count | Status | SQL Flow | Tables | External Calls |';
            $lines[] = '|---|---:|---|---|---|---|';
            foreach ($endpoint['patterns'] as $pattern) {
                $lines[] = '| ' . $this->tableCell($pattern['pattern_id']) . ' | ' . $this->tableCell($pattern['count']) . ' | ' . $this->tableCell(implode(', ', $pattern['statuses'])) . ' | ' . $this->tableCell($this->sqlFlowLabel($pattern['sql_flow'])) . ' | ' . $this->tableCell(implode(', ', $pattern['tables'])) . ' | ' . $this->tableCell($this->externalLabel($pattern['external_http'])) . ' |';
            }
            $lines[] = '';

            foreach ($endpoint['patterns'] as $pattern) {
                $lines[] = '### Pattern: ' . $pattern['pattern_id'];
                $lines[] = '';
                $lines[] = '- Observed: `' . $pattern['count'] . '`';
                $lines[] = '- Representative trace: `' . $pattern['representative_trace_id'] . '`';
                $lines[] = '';
                $lines[] = '#### SQL Statements';
                $lines[] = '';
                $lines[] = '| Step | Operation | Tables | Count | Example Source |';
                $lines[] = '|---:|---|---|---:|---|';
                foreach ($pattern['sql_flow'] as $step) {
                    $lines[] = '| ' . $this->tableCell($step['step']) . ' | ' . $this->tableCell($step['operation']) . ' | ' . $this->tableCell(implode(', ', $step['tables'])) . ' | ' . $this->tableCell($step['count']) . ' | ' . $this->tableCell($step['example_source']) . ' |';
                }
                if (count($pattern['sql_flow']) === 0) {
                    $lines[] = '| - | - | - | 0 | - |';
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines) . "\n";
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
}
