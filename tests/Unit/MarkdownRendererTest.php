<?php

namespace CoffeeR\Unearth\Tests\Unit;

use CoffeeR\Unearth\Report\Aggregator;
use CoffeeR\Unearth\Report\JsonlReader;
use CoffeeR\Unearth\Report\MarkdownRenderer;
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

        $this->assertStringContainsString('# Observed Behavior Evidence Report', $markdown);
        $this->assertStringContainsString('Observed entrypoints:', $markdown);
        $this->assertStringContainsString('Observed entrypoints are evidence views', $markdown);
        $this->assertStringContainsString('## POST /api/cart/add', $markdown);
        $this->assertStringContainsString('Entrypoint key:', $markdown);
        $this->assertStringContainsString('Migration unit assumption:', $markdown);
        $this->assertStringContainsString('Controller path:', $markdown);
        $this->assertStringContainsString('application/controllers/api/Cart.php', $markdown);
        $this->assertStringContainsString('Error rate:', $markdown);
        $this->assertStringContainsString('### Tables', $markdown);
        $this->assertStringContainsString('### Errors', $markdown);
        $this->assertStringContainsString('pattern-1', $markdown);
        $this->assertStringContainsString('SELECT M_SHOHIN -> INSERT T_CART', $markdown);
        $this->assertStringContainsString('SELECT M_SHOHIN -> SELECT T_FURIKAE_SHOHIN -> SELECT M_SHOHIN -> DELETE T_CART -> INSERT T_CART', $markdown);
        $this->assertStringContainsString('trace-substitute-1', $markdown);
        $this->assertStringContainsString('trace-ok-1', $markdown);
        $this->assertStringContainsString('### Behavior pattern: pattern-1', $markdown);
        $this->assertStringContainsString('#### Representative Observed Flow', $markdown);
        $this->assertStringContainsString('path (canonical):', $markdown);
        $this->assertStringContainsString('path (concrete):', $markdown);
        $this->assertStringNotContainsString('Example Source', $markdown);
        $this->assertStringNotContainsString('Avg duration', $markdown);
        $this->assertStringNotContainsString('P95 duration', $markdown);
        $this->assertStringNotContainsString('Max duration', $markdown);
    }

    public function testRendersTableCatalogDescriptions()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator('normalized', array('M_SHOHIN' => '商品マスタ。'));
        $renderer = new MarkdownRenderer();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl')));
        $markdown = $renderer->render($report);

        $this->assertStringContainsString('| M_SHOHIN | 商品マスタ。 |', $markdown);
        $this->assertStringContainsString('| T_CART |  |', $markdown);
    }

    public function testFlattensListShapeWithBracketNotation()
    {
        $renderer = new MarkdownRenderer();
        $markdown = $renderer->render(array(
            'generated_at' => 'now',
            'observed_entrypoint_count' => 1,
            'observed_entrypoints' => array(array(
                'entrypoint_key' => 'GET /items',
                'entrypoint_type' => 'http',
                'migration_unit_assumption' => 'not_assumed',
                'method' => 'GET',
                'path' => '/items',
                'controller' => null,
                'action' => null,
                'route' => null,
                'controller_path' => null,
                'observed_count' => 1,
                'status_codes' => array('200' => 1),
                'request_shape' => array(),
                'response_shape' => array(
                    'items' => array(array('id' => 'number', 'name' => 'string')),
                    'total' => 'number',
                ),
                'table_catalog' => array(),
                'patterns' => array(),
            )),
        ));

        $this->assertStringContainsString('items[].id', $markdown);
        $this->assertStringContainsString('items[].name', $markdown);
        $this->assertStringNotContainsString('items.0.id', $markdown);
        $this->assertStringNotContainsString('items.0', $markdown);
    }

    public function testEscapesMarkdownTableCells()
    {
        $renderer = new MarkdownRenderer();
        $markdown = $renderer->render(array(
            'generated_at' => 'now',
            'observed_entrypoint_count' => 1,
            'observed_entrypoints' => array(array(
                'entrypoint_key' => 'GET /pipe',
                'entrypoint_type' => 'http',
                'migration_unit_assumption' => 'not_assumed',
                'method' => 'GET',
                'path' => '/pipe',
                'controller' => null,
                'action' => null,
                'route' => null,
                'controller_path' => null,
                'observed_count' => 1,
                'status_codes' => array('200|ok' => 1),
                'request_shape' => array('field|name' => 'string`type'),
                'response_shape' => array(),
                'table_catalog' => array(array('table' => 'A|B', 'description' => 'desc|ription')),
                'patterns' => array(array(
                    'behavior_pattern_id' => 'pattern|1',
                    'count' => 1,
                    'statuses' => array('200|ok'),
                    'sql_flow' => array(),
                    'tables' => array('A|B'),
                    'external_http' => array(),
                    'representative_trace_id' => 'trace',
                    'representative_observed_flow' => array(
                        'trace_id' => 'trace',
                        'status' => 200,
                        'path_pattern' => '/pipe',
                        'path' => '/pipe',
                        'query_shape' => array(),
                        'query_raw' => null,
                        'request_shape' => array(),
                        'request_raw' => null,
                        'response_shape' => array(),
                        'sql' => array(),
                        'external_http' => array(),
                    ),
                )),
            )),
        ));

        $this->assertStringContainsString('200\\|ok', $markdown);
        $this->assertStringContainsString('field\\|name', $markdown);
        $this->assertStringContainsString('string\\`type', $markdown);
        $this->assertStringContainsString('pattern\\|1', $markdown);
        $this->assertStringContainsString('desc\\|ription', $markdown);
    }
}
