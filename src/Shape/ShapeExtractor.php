<?php

namespace CoffeeR\Unearther\Shape;

class ShapeExtractor
{
    public function extract($value)
    {
        if (is_array($value)) {
            if ($this->isList($value)) {
                if (count($value) === 0) {
                    return 'array';
                }

                $shape = null;
                foreach ($value as $item) {
                    $itemShape = $this->extract($item);
                    $shape = $shape === null ? $itemShape : $this->mergeShape($shape, $itemShape);
                }

                return array($shape);
            }

            $shape = array();
            foreach ($value as $key => $item) {
                $shape[$key] = $this->extract($item);
            }

            return $shape;
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
            return $left === $right ? $left : 'mixed';
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
}
