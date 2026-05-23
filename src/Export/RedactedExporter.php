<?php

namespace CoffeeR\Unearther\Export;

class RedactedExporter
{
    private $rawKeys = array(
        'statement_text' => true,
        'path_raw' => true,
        'query_raw' => true,
        'request_raw' => true,
        'response_raw' => true,
        'bind_raw' => true,
    );

    public function export(array $traces)
    {
        $out = array();
        foreach ($traces as $trace) {
            $tokenized = isset($trace['redaction']['tokenized']) ? (bool) $trace['redaction']['tokenized'] : false;
            $out[] = $this->redactValue($trace, $tokenized);
        }

        return $out;
    }

    private function redactValue($value, $tokenized)
    {
        if (!is_array($value)) {
            return $value;
        }

        $out = array();
        foreach ($value as $key => $child) {
            if (isset($this->rawKeys[$key])) {
                continue;
            }
            if (!$tokenized && $this->isTokenKey($key)) {
                continue;
            }
            $out[$key] = $this->redactValue($child, $tokenized);
            if ($this->isShapeKey($key) && $out[$key] === array()) {
                $out[$key] = new \stdClass();
            }
        }

        return $out;
    }

    private function isTokenKey($key)
    {
        $key = (string) $key;

        return substr($key, -7) === '_tokens' || substr($key, -10) === '_tokenized' || $key === 'statement_tokenized';
    }

    private function isShapeKey($key)
    {
        return substr((string) $key, -6) === '_shape';
    }
}
