<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit;

use CoffeeR\Ci3Unearth\Sink\JsonlSink;
use PHPUnit\Framework\TestCase;

class JsonlSinkTest extends TestCase
{
    public function testWritesOneTracePerLine()
    {
        $path = sys_get_temp_dir() . '/php-ci3-unearth-test-' . uniqid('', true) . '.jsonl';
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
        $base = sys_get_temp_dir() . '/php-ci3-unearth-test-' . uniqid('', true);
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

    public function testThrowsWhenWriteFails()
    {
        $path = sys_get_temp_dir() . '/php-ci3-unearth-sink-dir-' . uniqid('', true);
        mkdir($path);
        $sink = new JsonlSink($path);

        try {
            $sink->write(array('trace_id' => 'a'));
            $this->fail('Expected sink write failure to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('JSONL sink write failed.', $exception->getMessage());
        } finally {
            @rmdir($path);
        }
    }
}
