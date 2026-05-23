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
        $lines[] = '- Traces: `' . (isset($report['trace_count']) ? $report['trace_count'] : '-') . '`';
        $lines[] = '- Observed window: `' . (isset($report['observed_started_at_min']) ? $report['observed_started_at_min'] : '-') . '` to `' . (isset($report['observed_started_at_max']) ? $report['observed_started_at_max'] : '-') . '`';
        $lines[] = '- Value mode: `' . (isset($report['value_mode']) ? $report['value_mode'] : 'normalized') . '`';
        $lines[] = '';

        foreach ($report['endpoints'] as $endpoint) {
            $lines[] = '## ' . $endpoint['method'] . ' ' . $endpoint['path'];
            $lines[] = '';
            $lines[] = '- Observed requests: `' . $endpoint['observed_count'] . '`';
            $lines[] = '- Error rate: `' . $this->percent(isset($endpoint['error_rate']) ? $endpoint['error_rate'] : 0.0) . '`';
            $lines[] = '';
            $lines[] = '### Status Codes';
            $lines[] = '';
            $lines[] = '| Status | Count |';
            $lines[] = '|---|---:|';
            foreach ($endpoint['status_codes'] as $status => $count) {
                $lines[] = '| ' . $this->tableCell($status) . ' | ' . $this->tableCell($count) . ' |';
            }
            $lines[] = '';
            $lines[] = '### Errors';
            $lines[] = '';
            $lines[] = '| Error | Count | Representative Trace |';
            $lines[] = '|---|---:|---|';
            $errors = isset($endpoint['errors']) && is_array($endpoint['errors']) ? $endpoint['errors'] : array();
            foreach ($errors as $error) {
                $lines[] = '| ' . $this->tableCell($error['error']) . ' | ' . $this->tableCell($error['count']) . ' | ' . $this->tableCell($error['representative_trace_id']) . ' |';
            }
            if (count($errors) === 0) {
                $lines[] = '| - | 0 | - |';
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
                $lines[] = '| Step | Operation | Tables | Statement Hash | Statement (normalized) | Count | Example Source |';
                $lines[] = '|---:|---|---|---|---|---:|---|';
                foreach ($pattern['sql_flow'] as $step) {
                    $lines[] = '| ' . $this->tableCell($step['step']) . ' | ' . $this->tableCell($step['operation']) . ' | ' . $this->tableCell(implode(', ', $step['tables'])) . ' | ' . $this->tableCell(isset($step['statement_hash']) ? $step['statement_hash'] : '') . ' | ' . $this->tableCell(isset($step['statement_normalized']) ? $step['statement_normalized'] : '') . ' | ' . $this->tableCell($step['count']) . ' | ' . $this->tableCell($step['example_source']) . ' |';
                }
                if (count($pattern['sql_flow']) === 0) {
                    $lines[] = '| - | - | - | - | - | 0 | - |';
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
        $lines[] = '#### Representative Case';
        $lines[] = '';

        $rep = isset($pattern['representative']) && is_array($pattern['representative']) ? $pattern['representative'] : array();

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

    private function percent($rate)
    {
        return number_format(((float) $rate) * 100, 2, '.', '') . '%';
    }
}
