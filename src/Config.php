<?php

namespace CoffeeR\Unearth;

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
        if (!isset($this->values['redaction']) || !is_array($this->values['redaction'])) {
            $this->values['redaction'] = self::defaults()['redaction'];
        } else {
            $this->values['redaction'] = array_merge(self::defaults()['redaction'], $this->values['redaction']);
        }
        if (!isset($this->values['shape']) || !is_array($this->values['shape'])) {
            $this->values['shape'] = self::defaults()['shape'];
        } else {
            $this->values['shape'] = array_merge(self::defaults()['shape'], $this->values['shape']);
        }

        $this->values['failure_mode'] = FailureHandler::normalizeMode($this->values['failure_mode']);
        $this->values['sample_rate'] = $this->normalizeSampleRate($this->values['sample_rate']);
        $this->values['codeigniter3']['sql_capture'] = $this->normalizeSqlCapture($this->values['codeigniter3']['sql_capture']);
        $this->values['http']['max_body_bytes'] = max(0, (int) $this->values['http']['max_body_bytes']);
        $this->values['http']['endpoint_patterns'] = $this->normalizeEndpointPatterns($this->values['http']['endpoint_patterns']);
        $this->values['redaction']['token_length'] = max(8, min(64, (int) $this->values['redaction']['token_length']));
        if (!is_array($this->values['redaction']['deny_keys'])) {
            $this->values['redaction']['deny_keys'] = self::defaults()['redaction']['deny_keys'];
        }
        $this->values['shape']['max_depth'] = max(1, (int) $this->values['shape']['max_depth']);
        $this->values['shape']['max_items'] = max(1, (int) $this->values['shape']['max_items']);
    }

    public static function defaults()
    {
        return array(
            'enabled' => true,
            'service' => 'legacy-api',
            'framework' => 'codeigniter3',
            'environment' => 'production',
            'failure_mode' => FailureHandler::MODE_LOG,
            'sample_rate' => 0.1,
            'sink' => array(
                'type' => 'jsonl',
                'path' => sys_get_temp_dir() . '/php-unearth/observations-{date}.jsonl',
                'date_format' => 'Y-m-d',
            ),
            'codeigniter3' => array(
                'sql_capture' => 'sampled_query_history',
            ),
            'sql' => array(
                'capture_text' => false,
                'capture_bind_raw' => false,
            ),
            'redaction' => array(
                'secret' => null,
                'token_length' => 12,
                'deny_keys' => array('secret', 'token', 'password', 'passwd', 'authorization', 'cookie', 'set-cookie', 'api_key', 'apikey'),
            ),
            'shape' => array(
                'max_depth' => 6,
                'max_items' => 100,
            ),
            'http' => array(
                'capture_json_request_shape' => true,
                'capture_json_response_shape' => true,
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

    public function environment()
    {
        return $this->values['environment'];
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

    public function codeIgniter3SqlCapture()
    {
        return $this->values['codeigniter3']['sql_capture'];
    }

    public function captureSqlText()
    {
        return (bool) $this->values['sql']['capture_text'];
    }

    public function captureBindRaw()
    {
        return (bool) $this->values['sql']['capture_bind_raw'];
    }

    public function redactionSecret()
    {
        return $this->values['redaction']['secret'];
    }

    public function redactionTokenLength()
    {
        return (int) $this->values['redaction']['token_length'];
    }

    public function redactionDenyKeys()
    {
        return $this->values['redaction']['deny_keys'];
    }

    public function redactionMeta()
    {
        return array(
            'tokenized' => $this->redactionSecret() !== null && $this->redactionSecret() !== '',
            'token_format' => $this->redactionSecret() !== null && $this->redactionSecret() !== '' ? 'hmac-sha256:' . $this->redactionTokenLength() : null,
        );
    }

    public function shapeMaxDepth()
    {
        return (int) $this->values['shape']['max_depth'];
    }

    public function shapeMaxItems()
    {
        return (int) $this->values['shape']['max_items'];
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
        if (in_array($value, array('sampled_query_history', 'none'), true)) {
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
