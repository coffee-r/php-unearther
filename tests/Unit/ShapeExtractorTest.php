<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit;

use CoffeeR\Ci3Unearth\Shape\ShapeExtractor;
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
            'quantity' => 'number|string',
            'note' => 'string',
        )), $extractor->extract(array(
            array('sku' => 'A', 'quantity' => 2),
            array('sku' => 'B', 'quantity' => 'many', 'note' => 'gift'),
        )));
    }

    public function testLimitsDepthItemsAndObjectRecursion()
    {
        $extractor = new ShapeExtractor(2, 1);
        $object = new \stdClass();
        $object->self = $object;

        $shape = $extractor->extract(array(
            'first' => array('nested' => array('too_deep' => true)),
            'second' => 'skipped',
        ));

        $this->assertSame('truncated_depth', $shape['first']['nested']);
        $this->assertSame('boolean', $shape['__truncated__']);
        $this->assertSame(array('self' => 'recursive'), (new ShapeExtractor())->extract($object));
    }
}
