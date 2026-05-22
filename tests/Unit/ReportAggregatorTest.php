<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Report\Aggregator;
use CoffeeR\Unearther\Report\JsonlReader;
use PHPUnit\Framework\TestCase;

class ReportAggregatorTest extends TestCase
{
    public function testAggregatesEndpointPatterns()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl')));
        $endpoint = $report['endpoints'][0];

        $this->assertSame('POST', $endpoint['method']);
        $this->assertSame('/api/cart/add', $endpoint['path']);
        $this->assertSame(3, $endpoint['observed_count']);
        $this->assertCount(2, $endpoint['patterns']);
        $this->assertSame(2, $endpoint['patterns'][0]['count']);
        $this->assertSame(1, $endpoint['patterns'][1]['count']);
    }

    public function testExternalHttpParticipatesInPattern()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/order_create.jsonl')));
        $pattern = $report['endpoints'][0]['patterns'][0];

        $this->assertCount(1, $pattern['external_http']);
        $this->assertSame('payment.example.com', $pattern['external_http'][0]['host']);
    }
}
