<?php

namespace CoffeeR\Unearther\Report;

class JsonlReader
{
    private $warnings = array();

    public function read(array $paths)
    {
        $this->warnings = array();
        $traces = array();
        foreach ($paths as $path) {
            if (!is_file($path)) {
                $this->warnings[] = 'JSONL file not found: ' . $path;
                continue;
            }

            $handle = fopen($path, 'r');
            if (!$handle) {
                $this->warnings[] = 'JSONL file could not be opened: ' . $path;
                continue;
            }

            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->warnings[] = 'Invalid JSONL at ' . $path . ':' . $lineNumber . ' (' . json_last_error_msg() . ')';
                } elseif (is_array($decoded)) {
                    $traces[] = $decoded;
                } else {
                    $this->warnings[] = 'Invalid JSONL at ' . $path . ':' . $lineNumber . ' (line is not a JSON object)';
                }
            }

            fclose($handle);
        }

        return $traces;
    }

    public function warnings()
    {
        return $this->warnings;
    }
}
