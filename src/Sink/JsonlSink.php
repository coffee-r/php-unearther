<?php

namespace CoffeeR\Unearth\Sink;

class JsonlSink implements SinkInterface
{
    private $path;
    private $dateFormat;

    public function __construct($path, $dateFormat = 'Y-m-d')
    {
        $this->path = $path;
        $this->dateFormat = $dateFormat;
    }

    public function write(array $trace)
    {
        $line = json_encode($trace, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            throw new \RuntimeException('JSONL sink could not encode trace.');
        }

        $path = $this->resolvePath($trace);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            throw new \RuntimeException('JSONL sink directory could not be created.');
        }

        $written = @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('JSONL sink write failed.');
        }
    }

    public function resolvePath(array $trace)
    {
        if (strpos($this->path, '{date}') === false) {
            return $this->path;
        }

        $timestamp = time();
        if (isset($trace['started_at'])) {
            $parsed = strtotime($trace['started_at']);
            if ($parsed !== false) {
                $timestamp = $parsed;
            }
        }

        return str_replace('{date}', date($this->dateFormat, $timestamp), $this->path);
    }
}
