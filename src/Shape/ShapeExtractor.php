<?php

namespace CoffeeR\Ci3Unearth\Shape;

class ShapeExtractor
{
    private $maxDepth;
    private $maxItems;

    public function __construct($maxDepth = 6, $maxItems = 100)
    {
        $this->maxDepth = max(1, (int) $maxDepth);
        $this->maxItems = max(1, (int) $maxItems);
    }

    public function extract($value)
    {
        return $this->extractValue($value, 0, array());
    }

    private function extractValue($value, $depth, array $seenObjects)
    {
        if ($depth >= $this->maxDepth) {
            return 'truncated_depth';
        }

        if (is_array($value)) {
            if ($this->isList($value)) {
                if (count($value) === 0) {
                    return 'array';
                }

                $shape = null;
                $count = 0;
                foreach ($value as $item) {
                    if ($count >= $this->maxItems) {
                        $shape = $this->mergeShape($shape, array('__truncated__' => 'boolean'));
                        break;
                    }
                    $itemShape = $this->extractValue($item, $depth + 1, $seenObjects);
                    $shape = $shape === null ? $itemShape : $this->mergeShape($shape, $itemShape);
                    $count++;
                }

                return array($shape);
            }

            $shape = array();
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count >= $this->maxItems) {
                    $shape['__truncated__'] = 'boolean';
                    break;
                }
                $shape[$key] = $this->extractValue($item, $depth + 1, $seenObjects);
                $count++;
            }

            return $shape;
        }

        if (is_object($value)) {
            $hash = spl_object_hash($value);
            if (isset($seenObjects[$hash])) {
                return 'recursive';
            }
            $seenObjects[$hash] = true;

            $vars = get_object_vars($value);
            if (count($vars) === 0) {
                return 'object';
            }

            return $this->extractValue($vars, $depth + 1, $seenObjects);
        }

        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }

        return gettype($value);
    }

    private function isList(array $value)
    {
        if ($value === array()) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function mergeShape($left, $right)
    {
        if (!is_array($left) || !is_array($right)) {
            if ($left === $right) {
                return $left;
            }
            if (!is_array($left) && !is_array($right)) {
                return $this->unionType((string) $left, (string) $right);
            }

            return 'mixed';
        }

        foreach ($right as $key => $value) {
            if (!array_key_exists($key, $left)) {
                $left[$key] = $value;
            } else {
                $left[$key] = $this->mergeShape($left[$key], $value);
            }
        }

        return $left;
    }

    private function unionType($a, $b)
    {
        $parts = array_unique(array_merge(explode('|', $a), explode('|', $b)));
        sort($parts);

        return implode('|', $parts);
    }
}
