<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit;

use CoffeeR\Ci3Unearth\Http\JsonBodyShapeExtractor;
use PHPUnit\Framework\TestCase;

class JsonBodyShapeExtractorTest extends TestCase
{
    public function testExtractsJsonObjectShape()
    {
        $extractor = new JsonBodyShapeExtractor();

        $this->assertSame(array(
            'id' => 'number',
            'items' => array(array(
                'sku' => 'string',
                'quantity' => 'number',
            )),
        ), $extractor->extract('{"id":123,"items":[{"sku":"A","quantity":2}]}', 'application/json; charset=utf-8', 1024));
    }

    public function testAcceptsStructuredJsonContentTypes()
    {
        $extractor = new JsonBodyShapeExtractor();

        $this->assertSame(array('ok' => 'boolean'), $extractor->extract('{"ok":true}', 'application/vnd.api+json', 1024));
    }

    public function testSkipsInvalidNonJsonAndOversizedBodies()
    {
        $extractor = new JsonBodyShapeExtractor();

        $this->assertNull($extractor->extract('{"ok":', 'application/json', 1024));
        $this->assertNull($extractor->extract('{"ok":true}', 'text/plain', 1024));
        $this->assertNull($extractor->extract('{"ok":true}', 'application/json', 4));
    }
}
