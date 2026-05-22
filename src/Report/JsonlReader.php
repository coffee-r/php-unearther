<?php

namespace CoffeeR\Unearther\Report;

class JsonlReader
{
    public function read(array $paths)
    {
        $traces = array();
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $handle = fopen($path, 'r');
            if (!$handle) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $traces[] = $decoded;
                }
            }

            fclose($handle);
        }

        return $traces;
    }
}
