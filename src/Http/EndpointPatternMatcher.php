<?php

namespace CoffeeR\Ci3Unearth\Http;

class EndpointPatternMatcher
{
    public function match($method, $path, array $patterns)
    {
        $method = strtoupper((string) $method);
        $pathSegments = $this->segments($path);

        foreach ($patterns as $pattern) {
            if (!is_array($pattern) || !isset($pattern['method']) || !isset($pattern['path'])) {
                continue;
            }
            if (strtoupper((string) $pattern['method']) !== $method) {
                continue;
            }
            if (!$this->matchesPath($pathSegments, $this->segments($pattern['path']))) {
                continue;
            }

            $match = array(
                'path_pattern' => $pattern['path'],
            );
                return $match;
            }

        return null;
    }

    private function matchesPath(array $pathSegments, array $patternSegments)
    {
        if (count($pathSegments) !== count($patternSegments)) {
            return false;
        }

        foreach ($patternSegments as $index => $patternSegment) {
            if ($this->isPlaceholder($patternSegment)) {
                if ($pathSegments[$index] === '') {
                    return false;
                }
                continue;
            }

            if ($pathSegments[$index] !== $patternSegment) {
                return false;
            }
        }

        return true;
    }

    private function isPlaceholder($segment)
    {
        return preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', (string) $segment) === 1;
    }

    private function segments($path)
    {
        $path = parse_url((string) $path, PHP_URL_PATH);
        if ($path === false || $path === null) {
            $path = '';
        }

        $path = trim($path, '/');
        if ($path === '') {
            return array();
        }

        return explode('/', $path);
    }
}
