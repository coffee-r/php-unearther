<?php

namespace CoffeeR\Unearther;

class Config
{
    private $values;

    public function __construct(array $values = array())
    {
        $values = $this->normalizeValues($values);
        $this->values = array_merge(self::defaults(), $values);

        if (isset($this->values['sink']) && is_array($this->values['sink'])) {
            $this->values['sink'] = array_merge(self::defaults()['sink'], $this->values['sink']);
        }
        if (isset($this->values['codeigniter3']) && is_array($this->values['codeigniter3'])) {
            $this->values['codeigniter3'] = array_merge(self::defaults()['codeigniter3'], $this->values['codeigniter3']);
        }
        if (isset($this->values['http']) && is_array($this->values['http'])) {
            $this->values['http'] = array_merge(self::defaults()['http'], $this->values['http']);
        }
        if (!isset($this->values['sql']) || !is_array($this->values['sql'])) {
            $this->values['sql'] = self::defaults()['sql'];
        } else {
            $this->values['sql'] = array_merge(self::defaults()['sql'], $this->values['sql']);
        }

        $this->values['failure_mode'] = FailureHandler::normalizeMode($this->values['failure_mode']);
        $this->values['sample_rate'] = $this->normalizeSampleRate($this->values['sample_rate']);
        $this->values['codeigniter3']['sql_capture'] = $this->normalizeSqlCapture($this->values['codeigniter3']['sql_capture']);
        $this->values['http']['max_body_bytes'] = max(0, (int) $this->values['http']['max_body_bytes']);
        $this->values['http']['endpoint_patterns'] = $this->normalizeEndpointPatterns($this->values['http']['endpoint_patterns']);
    }

    public static function defaults()
    {
        return array(
            'enabled' => true,
            'service' => 'legacy-api',
            'framework' => 'php',
            'failure_mode' => FailureHandler::MODE_THROW,
            'sample_rate' => 1.0,
            'sink' => array(
                'type' => 'jsonl',
                'path' => sys_get_temp_dir() . '/php-unearther/observations-{date}.jsonl',
                'date_format' => 'Y-m-d',
            ),
            'codeigniter3' => array(
                'sql_capture' => 'query_history',
            ),
            'sql' => array(
                'capture_text' => false,
            ),
            'http' => array(
                'capture_json_request_shape' => true,
                'capture_json_response_shape' => false,
                'max_body_bytes' => 65536,
                'endpoint_patterns' => array(),
            ),
        );
    }

    public static function fromArray(array $values)
    {
        return new self($values);
    }

    public function isEnabled()
    {
        return (bool) $this->values['enabled'];
    }

    public function service()
    {
        return $this->values['service'];
    }

    public function framework()
    {
        return $this->values['framework'];
    }

    public function sampleRate()
    {
        return (float) $this->values['sample_rate'];
    }

    public function failureMode()
    {
        return $this->values['failure_mode'];
    }

    public function sinkType()
    {
        return $this->values['sink']['type'];
    }

    public function sinkPath()
    {
        return $this->values['sink']['path'];
    }

    public function sinkDateFormat()
    {
        return $this->values['sink']['date_format'];
    }

    public function codeIgniter3CaptureQueryHistory()
    {
        return $this->codeIgniter3SqlCapture() === 'query_history';
    }

    public function codeIgniter3SqlCapture()
    {
        return $this->values['codeigniter3']['sql_capture'];
    }

    public function captureSqlText()
    {
        return (bool) $this->values['sql']['capture_text'];
    }

    public function captureJsonRequestShape()
    {
        return (bool) $this->values['http']['capture_json_request_shape'];
    }

    public function captureJsonResponseShape()
    {
        return (bool) $this->values['http']['capture_json_response_shape'];
    }

    public function maxBodyBytes()
    {
        return (int) $this->values['http']['max_body_bytes'];
    }

    public function endpointPatterns()
    {
        return $this->values['http']['endpoint_patterns'];
    }

    public function toArray()
    {
        return $this->values;
    }

    private function normalizeValues(array $values)
    {
        if (isset($values['codeigniter3']) && is_array($values['codeigniter3'])) {
            $legacy = $values['codeigniter3'];
            if (array_key_exists('capture_query_history', $legacy) && !array_key_exists('sql_capture', $legacy)) {
                $values['codeigniter3']['sql_capture'] = $legacy['capture_query_history'] ? 'query_history' : 'none';
            }
            unset($values['codeigniter3']['capture_query_history']);
        }

        return $values;
    }

    private function normalizeSampleRate($value)
    {
        $value = (float) $value;
        if ($value < 0.0) {
            return 0.0;
        }
        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }

    private function normalizeSqlCapture($value)
    {
        $value = strtolower((string) $value);
        if (in_array($value, array('query_history', 'observed_db', 'none'), true)) {
            return $value;
        }

        return self::defaults()['codeigniter3']['sql_capture'];
    }

    private function normalizeEndpointPatterns($patterns)
    {
        if (!is_array($patterns)) {
            return array();
        }

        $normalized = array();
        foreach ($patterns as $pattern) {
            if (!is_array($pattern) || !isset($pattern['method']) || !isset($pattern['path'])) {
                continue;
            }

            $method = strtoupper(trim((string) $pattern['method']));
            $path = trim((string) $pattern['path']);
            if ($method === '' || $path === '') {
                continue;
            }

            $item = array(
                'method' => $method,
                'path' => $path,
            );
            if (isset($pattern['name']) && trim((string) $pattern['name']) !== '') {
                $item['name'] = trim((string) $pattern['name']);
            }

            $normalized[] = $item;
        }

        return $normalized;
    }
}
