<?php

namespace CoffeeR\Unearther\Report;

class Aggregator
{
    public function aggregate(array $traces)
    {
        $endpoints = array();

        foreach ($traces as $trace) {
            $http = isset($trace['http']) && is_array($trace['http']) ? $trace['http'] : array();
            $method = isset($http['method']) ? strtoupper($http['method']) : 'UNKNOWN';
            $path = isset($http['endpoint_path']) ? $http['endpoint_path'] : (isset($http['path']) ? $http['path'] : 'unknown');
            $endpointKey = $method . ' ' . $path;

            if (!isset($endpoints[$endpointKey])) {
                $endpoints[$endpointKey] = $this->emptyEndpoint($method, $path);
            }

            $endpoint =& $endpoints[$endpointKey];
            $endpoint['observed_count']++;
            $endpoint['durations'][] = isset($trace['duration_ms']) ? (int) $trace['duration_ms'] : 0;

            if (isset($http['status'])) {
                $status = (string) $http['status'];
                if (!isset($endpoint['status_codes'][$status])) {
                    $endpoint['status_codes'][$status] = 0;
                }
                $endpoint['status_codes'][$status]++;
            }

            if (isset($http['route'])) {
                $endpoint['routes'][$http['route']] = true;
            }
            if (isset($http['controller'])) {
                $endpoint['controllers'][$http['controller']] = true;
            }
            if (isset($http['endpoint_name'])) {
                $endpoint['endpoint_names'][$http['endpoint_name']] = true;
            }

            $endpoint['request_shape'] = $this->mergeShape($endpoint['request_shape'], isset($http['request_shape']) ? $http['request_shape'] : array());
            $endpoint['response_shape'] = $this->mergeShape($endpoint['response_shape'], isset($http['response_shape']) ? $http['response_shape'] : array());

            $errors = $this->errorEvents($trace);
            if (count($errors) > 0) {
                $endpoint['error_count']++;
                foreach ($errors as $error) {
                    $label = $this->errorLabel($error);
                    if (!isset($endpoint['errors'][$label])) {
                        $endpoint['errors'][$label] = array(
                            'error' => $label,
                            'count' => 0,
                            'representative_trace_id' => isset($trace['trace_id']) ? $trace['trace_id'] : null,
                        );
                    }
                    $endpoint['errors'][$label]['count']++;
                }
            }

            $signature = $this->patternSignature($trace);
            if (!isset($endpoint['patterns'][$signature])) {
                $endpoint['patterns'][$signature] = array(
                    'pattern_id' => 'pattern-' . (count($endpoint['patterns']) + 1),
                    'signature' => $signature,
                    'count' => 0,
                    'statuses' => array(),
                    'sql_flow' => array(),
                    'tables' => array(),
                    'external_http' => array(),
                    'representative_trace_id' => isset($trace['trace_id']) ? $trace['trace_id'] : null,
                );
            }

            $pattern =& $endpoint['patterns'][$signature];
            $pattern['count']++;
            if (isset($http['status'])) {
                $pattern['statuses'][(string) $http['status']] = true;
            }

            foreach ($this->sqlFlow($trace) as $index => $step) {
                if (!isset($pattern['sql_flow'][$index])) {
                    $pattern['sql_flow'][$index] = array(
                        'step' => $index + 1,
                        'operation' => $step['operation'],
                        'tables' => $step['tables'],
                        'statement_hash' => $step['statement_hash'],
                        'fingerprint_sql' => $step['fingerprint_sql'],
                        'count' => 0,
                        'example_source' => $this->sourceLabel($step['caller']),
                    );
                }
                $pattern['sql_flow'][$index]['count']++;

                foreach ($step['tables'] as $table) {
                    $pattern['tables'][$table] = true;
                }
            }

            foreach ($this->externalFlow($trace) as $external) {
                $key = $external['method'] . ' ' . $external['host'] . $external['path'];
                $pattern['external_http'][$key] = $external;
            }

            unset($pattern);
            unset($endpoint);
        }

        foreach ($endpoints as &$endpoint) {
            $endpoint['avg_duration_ms'] = $this->avg($endpoint['durations']);
            $endpoint['p95_duration_ms'] = $this->percentile($endpoint['durations'], 95);
            $endpoint['max_duration_ms'] = count($endpoint['durations']) ? max($endpoint['durations']) : 0;
            $endpoint['error_rate'] = $endpoint['observed_count'] > 0 ? round($endpoint['error_count'] / $endpoint['observed_count'], 4) : 0.0;
            unset($endpoint['durations']);

            $endpoint['routes'] = array_keys($endpoint['routes']);
            $endpoint['controllers'] = array_keys($endpoint['controllers']);
            $endpoint['endpoint_names'] = array_keys($endpoint['endpoint_names']);
            $endpoint['errors'] = array_values($endpoint['errors']);

            foreach ($endpoint['patterns'] as &$pattern) {
                $pattern['statuses'] = array_keys($pattern['statuses']);
                $pattern['tables'] = array_keys($pattern['tables']);
                $pattern['external_http'] = array_values($pattern['external_http']);
                $pattern['sql_flow'] = array_values($pattern['sql_flow']);
            }
            unset($pattern);

            $endpoint['patterns'] = array_values($endpoint['patterns']);
        }
        unset($endpoint);

        ksort($endpoints);

        return array(
            'generated_at' => date('c'),
            'endpoint_count' => count($endpoints),
            'endpoints' => array_values($endpoints),
        );
    }

    private function emptyEndpoint($method, $path)
    {
        return array(
            'method' => $method,
            'path' => $path,
            'observed_count' => 0,
            'routes' => array(),
            'controllers' => array(),
            'endpoint_names' => array(),
            'status_codes' => array(),
            'durations' => array(),
            'avg_duration_ms' => 0,
            'p95_duration_ms' => 0,
            'max_duration_ms' => 0,
            'error_count' => 0,
            'error_rate' => 0.0,
            'errors' => array(),
            'request_shape' => array(),
            'response_shape' => array(),
            'patterns' => array(),
        );
    }

    private function patternSignature(array $trace)
    {
        $parts = array();
        foreach ($this->sqlFlow($trace) as $step) {
            $tables = count($step['tables']) ? implode('+', $step['tables']) : 'NO_TABLE';
            $part = $step['operation'] . ':' . $tables;
            if ($step['statement_hash'] !== '') {
                $part .= ':' . $step['statement_hash'];
            }
            $parts[] = $part;
        }
        foreach ($this->externalFlow($trace) as $external) {
            $parts[] = 'HTTP:' . $external['method'] . ':' . $external['host'] . $external['path'];
        }

        if (count($parts) === 0) {
            return 'no-observed-io';
        }

        return implode(' -> ', $parts);
    }

    private function sqlFlow(array $trace)
    {
        $flow = array();
        $items = isset($trace['sql']) && is_array($trace['sql']) ? $trace['sql'] : array();
        foreach ($items as $item) {
            $flow[] = array(
                'operation' => isset($item['operation']) ? $item['operation'] : 'UNKNOWN',
                'tables' => isset($item['tables']) && is_array($item['tables']) ? $item['tables'] : array(),
                'statement_hash' => isset($item['statement_hash']) ? (string) $item['statement_hash'] : '',
                'fingerprint_sql' => isset($item['fingerprint_sql']) ? (string) $item['fingerprint_sql'] : '',
                'caller' => isset($item['caller']) && is_array($item['caller']) ? $item['caller'] : array(),
            );
        }

        return $flow;
    }

    private function externalFlow(array $trace)
    {
        $flow = array();
        $items = isset($trace['external_http']) && is_array($trace['external_http']) ? $trace['external_http'] : array();
        foreach ($items as $item) {
            $flow[] = array(
                'method' => isset($item['method']) ? strtoupper($item['method']) : 'GET',
                'host' => isset($item['host']) ? $item['host'] : '',
                'path' => isset($item['path']) ? $item['path'] : '',
            );
        }

        return $flow;
    }

    private function sourceLabel(array $caller)
    {
        if (!isset($caller['file'])) {
            return '';
        }

        $label = basename($caller['file']);
        if (isset($caller['line'])) {
            $label .= ':' . $caller['line'];
        }

        return $label;
    }

    private function errorEvents(array $trace)
    {
        return isset($trace['errors']) && is_array($trace['errors']) ? $trace['errors'] : array();
    }

    private function errorLabel(array $error)
    {
        $type = isset($error['type']) ? (string) $error['type'] : (isset($error['class']) ? (string) $error['class'] : 'error');
        $message = isset($error['message']) ? (string) $error['message'] : '';
        if ($message === '') {
            return $type;
        }

        return $type . ': ' . $message;
    }

    private function mergeShape($left, $right)
    {
        if (!is_array($left)) {
            return $right;
        }
        if (!is_array($right)) {
            return $left;
        }

        foreach ($right as $key => $value) {
            if (!array_key_exists($key, $left)) {
                $left[$key] = $value;
            } elseif (is_array($left[$key]) && is_array($value)) {
                $left[$key] = $this->mergeShape($left[$key], $value);
            } elseif ($left[$key] !== $value) {
                $left[$key] = 'mixed';
            }
        }

        return $left;
    }

    private function avg(array $values)
    {
        if (count($values) === 0) {
            return 0;
        }

        return (int) round(array_sum($values) / count($values));
    }

    private function percentile(array $values, $percentile)
    {
        if (count($values) === 0) {
            return 0;
        }

        sort($values);
        $index = (int) ceil(($percentile / 100) * count($values)) - 1;
        if ($index < 0) {
            $index = 0;
        }

        return $values[$index];
    }
}
