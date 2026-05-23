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
        $this->assertStringContainsString('Error rate:', $markdown);
        $this->assertStringContainsString('### Errors', $markdown);
        $this->assertStringContainsString('pattern-1', $markdown);
        $this->assertStringContainsString('SELECT M_SHOHIN -> INSERT T_CART', $markdown);
        $this->assertStringContainsString('trace-ok-1', $markdown);
    }

    public function testEscapesMarkdownTableCells()
    {
        $renderer = new MarkdownRenderer();
        $markdown = $renderer->render(array(
            'generated_at' => 'now',
            'endpoint_count' => 1,
            'endpoints' => array(array(
                'method' => 'GET',
                'path' => '/pipe',
                'observed_count' => 1,
                'avg_duration_ms' => 1,
                'p95_duration_ms' => 1,
                'max_duration_ms' => 1,
                'status_codes' => array('200|ok' => 1),
                'request_shape' => array('field|name' => 'string`type'),
                'response_shape' => array(),
                'patterns' => array(array(
                    'pattern_id' => 'pattern|1',
                    'count' => 1,
                    'statuses' => array('200|ok'),
                    'sql_flow' => array(),
                    'tables' => array('A|B'),
                    'external_http' => array(),
                    'representative_trace_id' => 'trace',
                )),
            )),
        ));

        $this->assertStringContainsString('200\\|ok', $markdown);
        $this->assertStringContainsString('field\\|name', $markdown);
        $this->assertStringContainsString('string\\`type', $markdown);
        $this->assertStringContainsString('pattern\\|1', $markdown);
    }
}
