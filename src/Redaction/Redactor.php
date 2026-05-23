<?php

namespace CoffeeR\Unearth\Redaction;

class Redactor
{
    private $secret;
    private $tokenLength;
    private $denyKeys;

    public function __construct($secret = null, $tokenLength = 12, array $denyKeys = array())
    {
        $this->secret = $secret === null ? null : (string) $secret;
        $this->tokenLength = max(8, min(64, (int) $tokenLength));
        $this->denyKeys = array();
        foreach ($denyKeys as $key) {
            $this->denyKeys[] = strtolower((string) $key);
        }
    }

    public function hasSecret()
    {
        return $this->secret !== null && $this->secret !== '';
    }

    public function token($value)
    {
        if (!$this->hasSecret()) {
            return null;
        }

        $hash = hash_hmac('sha256', $this->canonicalValue($value), $this->secret);

        return '{p-' . substr($hash, 0, $this->tokenLength) . '}';
    }

    public function tokens($value, $key = null)
    {
        if ($key !== null && $this->isDeniedKey($key)) {
            return '{redacted}';
        }
        if (!$this->hasSecret()) {
            return null;
        }
        if (is_array($value)) {
            $tokens = array();
            foreach ($value as $childKey => $childValue) {
                $tokens[$childKey] = $this->tokens($childValue, $childKey);
            }

            return $tokens;
        }
        if (is_object($value)) {
            return '{object}';
        }

        return $this->token($value);
    }

    public function tokenizedSql($sql)
    {
        if (!$this->hasSecret()) {
            return null;
        }

        $self = $this;
        $sql = preg_replace_callback("/'(?:''|[^'])*'/", function ($matches) use ($self) {
            $value = substr($matches[0], 1, -1);
            $value = str_replace("''", "'", $value);

            return $self->token($value);
        }, (string) $sql);

        return preg_replace_callback('/\b\d+(?:\.\d+)?\b/', function ($matches) use ($self) {
            return $self->token($matches[0]);
        }, $sql);
    }

    public function isDeniedKey($key)
    {
        $key = strtolower((string) $key);
        foreach ($this->denyKeys as $denied) {
            if ($denied !== '' && strpos($key, $denied) !== false) {
                return true;
            }
        }

        return false;
    }

    private function canonicalValue($value)
    {
        if (is_null($value)) {
            return 'null:null';
        }
        if (is_bool($value)) {
            return 'boolean:' . ($value ? 'true' : 'false');
        }
        if (is_int($value) || is_float($value)) {
            return 'number:' . $this->canonicalNumber($value);
        }
        if (is_array($value)) {
            return 'array:' . json_encode($value);
        }
        if (is_string($value) && $this->isCanonicalNumericString($value)) {
            return 'number:' . $this->canonicalNumber($value);
        }

        return 'string:' . (string) $value;
    }

    private function isCanonicalNumericString($value)
    {
        return preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', $value) === 1;
    }

    private function canonicalNumber($value)
    {
        $value = (string) $value;
        if (strpos($value, '.') === false) {
            return ltrim($value, '+');
        }

        $value = rtrim($value, '0');
        $value = rtrim($value, '.');

        return $value === '-0' ? '0' : $value;
    }
}
