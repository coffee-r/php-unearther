<?php

namespace CoffeeR\Unearther\Schema;

class ObservationValidator
{
    private $schema;

    public function __construct($schemaPath = null)
    {
        $schemaPath = $schemaPath ?: __DIR__ . '/../../docs/schema/observation-v1.schema.json';
        $json = is_file($schemaPath) ? file_get_contents($schemaPath) : false;
        if ($json === false) {
            throw new \InvalidArgumentException('Schema file not found: ' . $schemaPath);
        }

        $schema = json_decode($json, true);
        if (!is_array($schema) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON schema: ' . $schemaPath);
        }

        $this->schema = $schema;
    }

    public function validate(array $trace)
    {
        return $this->validateAgainst($this->schema, $trace, '$');
    }

    private function validateAgainst(array $schema, $value, $path)
    {
        $errors = array();

        if (array_key_exists('const', $schema) && $value !== $schema['const']) {
            $errors[] = $path . ' must equal ' . json_encode($schema['const']);
        }
        if (array_key_exists('enum', $schema) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = $path . ' must be one of ' . json_encode($schema['enum']);
        }
        if (isset($schema['type'])) {
            $types = is_array($schema['type']) ? $schema['type'] : array($schema['type']);
            if (!$this->matchesAnyType($value, $types)) {
                $errors[] = $path . ' must be ' . implode('|', $types);
                return $errors;
            }
        }
        if (isset($schema['minimum']) && is_numeric($value) && $value < $schema['minimum']) {
            $errors[] = $path . ' must be >= ' . $schema['minimum'];
        }
        if (isset($schema['maximum']) && is_numeric($value) && $value > $schema['maximum']) {
            $errors[] = $path . ' must be <= ' . $schema['maximum'];
        }

        if ($this->schemaTypeIncludes($schema, 'object') && is_array($value)) {
            $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : array();
            foreach ($required as $key) {
                if (!array_key_exists($key, $value)) {
                    $errors[] = 'missing required ' . $path . '.' . $key;
                }
            }

            $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : array();
            foreach ($properties as $key => $childSchema) {
                if (array_key_exists($key, $value) && is_array($childSchema)) {
                    $errors = array_merge($errors, $this->validateAgainst($childSchema, $value[$key], $path . '.' . $key));
                }
            }
        }

        if ($this->schemaTypeIncludes($schema, 'array') && is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach ($value as $index => $item) {
                $errors = array_merge($errors, $this->validateAgainst($schema['items'], $item, $path . '[' . $index . ']'));
            }
        }

        return $errors;
    }

    private function matchesAnyType($value, array $types)
    {
        foreach ($types as $type) {
            if ($this->matchesType($value, $type)) {
                return true;
            }
        }

        return false;
    }

    private function matchesType($value, $type)
    {
        switch ($type) {
            case 'object':
                return is_array($value);
            case 'array':
                return is_array($value);
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value);
            case 'number':
                return is_int($value) || is_float($value);
            case 'boolean':
                return is_bool($value);
            case 'null':
                return $value === null;
            default:
                return true;
        }
    }

    private function schemaTypeIncludes(array $schema, $type)
    {
        if (!isset($schema['type'])) {
            return false;
        }

        $types = is_array($schema['type']) ? $schema['type'] : array($schema['type']);

        return in_array($type, $types, true);
    }
}
