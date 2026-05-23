<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Shape\ShapeExtractor;
use PHPUnit\Framework\TestCase;

class ShapeExtractorTest extends TestCase
{
    public function testExtractsNestedShape()
    {
        $extractor = new ShapeExtractor();

        $this->assertSame(array(
            'id' => 'number',
            'name' => 'string',
            'active' => 'boolean',
            'items' => array(array(
                'sku' => 'string',
                'quantity' => 'number',
            )),
        ), $extractor->extract(array(
            'id' => 123,
            'name' => 'coffee',
            'active' => true,
            'items' => array(array('sku' => 'A', 'quantity' => 2)),
        )));
    }

    public function testMergesListElementShapes()
    {
        $extractor = new ShapeExtractor();

        $this->assertSame(array(array(
            'sku' => 'string',
            'quantity' => 'mixed',
            'note' => 'string',
        )), $extractor->extract(array(
            array('sku' => 'A', 'quantity' => 2),
            array('sku' => 'B', 'quantity' => 'many', 'note' => 'gift'),
        )));
    }
}
