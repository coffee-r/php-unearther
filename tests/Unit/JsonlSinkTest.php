<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Sink\JsonlSink;
use PHPUnit\Framework\TestCase;

class JsonlSinkTest extends TestCase
{
    public function testWritesOneTracePerLine()
    {
        $path = sys_get_temp_dir() . '/php-unearther-test-' . uniqid('', true) . '.jsonl';
        $sink = new JsonlSink($path);

        $sink->write(array('trace_id' => 'a'));
        $sink->write(array('trace_id' => 'b'));

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        @unlink($path);

        $this->assertCount(2, $lines);
        $this->assertSame('a', json_decode($lines[0], true)['trace_id']);
        $this->assertSame('b', json_decode($lines[1], true)['trace_id']);
    }

    public function testRotatesPathByTraceDate()
    {
        $base = sys_get_temp_dir() . '/php-unearther-test-' . uniqid('', true);
        $path = $base . '/observations-{date}.jsonl';
        $sink = new JsonlSink($path);

        $sink->write(array('trace_id' => 'a', 'started_at' => '2026-06-01T10:00:00+09:00'));
        $sink->write(array('trace_id' => 'b', 'started_at' => '2026-06-02T10:00:00+09:00'));

        $first = $base . '/observations-2026-06-01.jsonl';
        $second = $base . '/observations-2026-06-02.jsonl';

        $this->assertFileExists($first);
        $this->assertFileExists($second);
        $this->assertSame('a', json_decode(file($first, FILE_IGNORE_NEW_LINES)[0], true)['trace_id']);
        $this->assertSame('b', json_decode(file($second, FILE_IGNORE_NEW_LINES)[0], true)['trace_id']);

        @unlink($first);
        @unlink($second);
        @rmdir($base);
    }
}
