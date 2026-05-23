<?php

namespace CoffeeR\Unearth\Tests\Unit;

use CoffeeR\Unearth\Report\JsonlReader;
use PHPUnit\Framework\TestCase;

class JsonlReaderTest extends TestCase
{
    public function testCollectsWarningsForMissingAndInvalidJsonl()
    {
        $path = sys_get_temp_dir() . '/php-unearth-reader-test-' . uniqid('', true) . '.jsonl';
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

    public function testWarnsWhenLineIsValidJsonButNotAnObjectOrArray()
    {
        $path = sys_get_temp_dir() . '/php-unearth-reader-nonobj-' . uniqid('', true) . '.jsonl';
        file_put_contents($path, "\"plain string\"\n42\ntrue\n{\"trace_id\":\"ok\"}\n");

        $reader = new JsonlReader();
        $traces = $reader->read(array($path));

        @unlink($path);

        $this->assertCount(1, $traces);
        $this->assertSame('ok', $traces[0]['trace_id']);
        $warnings = $reader->warnings();
        $this->assertCount(3, $warnings);
        foreach ($warnings as $warning) {
            $this->assertStringContainsString('line is not a JSON object', $warning);
        }
    }
}
