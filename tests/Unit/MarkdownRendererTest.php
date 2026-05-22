<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Report\Aggregator;
use CoffeeR\Unearther\Report\JsonlReader;
use CoffeeR\Unearther\Report\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class MarkdownRendererTest extends TestCase
{
    public function testRendersObservedBehaviorReport()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();
        $renderer = new MarkdownRenderer();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl')));
        $markdown = $renderer->render($report);

        $this->assertStringContainsString('# Observed API Behavior Report', $markdown);
        $this->assertStringContainsString('## POST /api/cart/add', $markdown);
        $this->assertStringContainsString('pattern-1', $markdown);
        $this->assertStringContainsString('SELECT M_SHOHIN -> INSERT T_CART', $markdown);
        $this->assertStringContainsString('trace-ok-1', $markdown);
    }
}
