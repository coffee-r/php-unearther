<?php

namespace CoffeeR\Unearther\Http;

use CoffeeR\Unearther\Shape\ShapeExtractor;

class JsonBodyShapeExtractor
{
    private $shapeExtractor;

    public function __construct(?ShapeExtractor $shapeExtractor = null)
    {
        $this->shapeExtractor = $shapeExtractor ?: new ShapeExtractor();
    }

    public function extract($body, $contentType, $maxBytes)
    {
        if (!is_string($body) || !$this->isJsonContentType($contentType)) {
            return null;
        }

        if (strlen($body) > (int) $maxBytes) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $this->shapeExtractor->extract($decoded);
    }

    public function isJsonContentType($contentType)
    {
        if (!is_string($contentType) || trim($contentType) === '') {
            return false;
        }

        $parts = explode(';', strtolower($contentType), 2);
        $type = trim($parts[0]);

        return $type === 'application/json' || substr($type, -5) === '+json';
    }
}
