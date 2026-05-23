<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Report\JsonlReader;
use PHPUnit\Framework\TestCase;

class JsonlReaderTest extends TestCase
{
    public function testCollectsWarningsForMissingAndInvalidJsonl()
    {
        $path = sys_get_temp_dir() . '/php-unearther-reader-test-' . uniqid('', true) . '.jsonl';
        file_put_contents($path, "{\"trace_id\":\"ok\"}\nnot-json\n");

        $reader = new JsonlReader();
        $traces = $reader->read(array($path, $path . '.missing'));

        @unlink($path);

        $this->assertCount(1, $traces);
        $this->assertSame('ok', $traces[0]['trace_id']);
        $this->assertCount(2, $reader->warnings());
        $this->assertStringContainsString('Invalid JSONL', $reader->warnings()[0]);
        $this->assertStringContainsString('JSONL file not found', $reader->warnings()[1]);
    }
}
