<?php

namespace CoffeeR\Unearth\Tests\Unit;

use CoffeeR\Unearth\Report\Aggregator;
use CoffeeR\Unearth\Report\JsonlReader;
use PHPUnit\Framework\TestCase;

class ReportAggregatorTest extends TestCase
{
    public function testAggregatesObservedEntrypointPatterns()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl')));
        $entrypoint = $report['observed_entrypoints'][0];

        $this->assertSame('observed_behavior', $report['report_kind']);
        $this->assertSame(1, $report['observed_entrypoint_count']);
        $this->assertArrayNotHasKey('endpoint_count', $report);
        $this->assertArrayNotHasKey('endpoints', $report);
        $this->assertSame('POST /api/cart/add', $entrypoint['entrypoint_key']);
        $this->assertSame('http', $entrypoint['entrypoint_type']);
        $this->assertSame('not_assumed', $entrypoint['migration_unit_assumption']);
        $this->assertSame('POST', $entrypoint['method']);
        $this->assertSame('/api/cart/add', $entrypoint['path']);
        $this->assertSame(3, $entrypoint['observed_count']);
        $this->assertCount(2, $entrypoint['patterns']);
        $this->assertSame('pattern-1', $entrypoint['patterns'][0]['behavior_pattern_id']);
        $this->assertSame(2, $entrypoint['patterns'][0]['count']);
        $this->assertSame(1, $entrypoint['patterns'][1]['count']);
        $this->assertArrayNotHasKey('avg_duration_ms', $entrypoint);
        $this->assertArrayNotHasKey('p95_duration_ms', $entrypoint);
        $this->assertArrayNotHasKey('max_duration_ms', $entrypoint);
    }

    public function testRepresentativeCaseIsAttachedToPattern()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl')));
        $pattern = $report['observed_entrypoints'][0]['patterns'][0];
        $rep = $pattern['representative_observed_flow'];

        $this->assertSame('pattern-1', $pattern['behavior_pattern_id']);
        $this->assertStringContainsString('STATUS:200', $pattern['observed_flow_signature']);
        $this->assertArrayNotHasKey('pattern_id', $pattern);
        $this->assertArrayNotHasKey('signature', $pattern);
        $this->assertArrayNotHasKey('representative', $pattern);
        $this->assertSame('trace-ok-1', $rep['trace_id']);
        $this->assertSame(200, $rep['status']);
        $this->assertSame('/api/cart/add', $rep['path_pattern']);
        $this->assertSame('/api/cart/add', $rep['path']);
        $this->assertCount(2, $rep['sql']);
        $this->assertSame('SELECT * FROM M_SHOHIN WHERE item_code = {parameter}', $rep['sql'][0]['statement_normalized']);
        $this->assertSame(array(), $rep['external_http']);
    }

    public function testExternalHttpParticipatesInPattern()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/order_create.jsonl')));
        $pattern = $report['observed_entrypoints'][0]['patterns'][0];

        $this->assertCount(1, $pattern['external_http']);
        $this->assertSame('payment.example.com', $pattern['external_http'][0]['host']);
    }

    public function testUsesCanonicalEntrypointPathWhenPresent()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array('trace_id' => 'a', 'http' => array('method' => 'GET', 'path' => '/api/users/1', 'path_pattern' => '/api/users/{id}', 'status' => 200), 'sql' => array(), 'external_http' => array(), 'errors' => array()),
            array('trace_id' => 'b', 'http' => array('method' => 'GET', 'path' => '/api/users/2', 'path_pattern' => '/api/users/{id}', 'status' => 200), 'sql' => array(), 'external_http' => array(), 'errors' => array()),
        ));

        $this->assertSame(1, $report['observed_entrypoint_count']);
        $this->assertSame('/api/users/{id}', $report['observed_entrypoints'][0]['path']);
        $this->assertSame(2, $report['observed_entrypoints'][0]['observed_count']);
    }

    public function testAggregatesErrors()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array('trace_id' => 'a', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'errors' => array(array('type' => 'warning', 'message' => 'slow query'))),
            array('trace_id' => 'b', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 500), 'errors' => array(array('type' => 'warning', 'message' => 'slow query'))),
            array('trace_id' => 'c', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'errors' => array()),
        ));
        $entrypoint = $report['observed_entrypoints'][0];

        $this->assertSame(2, $entrypoint['error_count']);
        $this->assertSame(0.6667, $entrypoint['error_rate']);
        $this->assertSame('warning: slow query', $entrypoint['errors'][0]['error']);
        $this->assertSame(2, $entrypoint['errors'][0]['count']);
    }

    public function testSqlStatementHashParticipatesInPattern()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array(
                'trace_id' => 'a',
                'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200),
                'sql' => array(array(
                    'operation' => 'SELECT',
                    'tables' => array('USERS'),
                    'statement_hash' => 'sha256:first',
                    'statement_normalized' => 'select * from users where id = {parameter}',
                    'statement_text' => 'select * from users where id = 1',
                )),
            ),
            array(
                'trace_id' => 'b',
                'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200),
                'sql' => array(array(
                    'operation' => 'SELECT',
                    'tables' => array('USERS'),
                    'statement_hash' => 'sha256:first',
                    'statement_normalized' => 'select * from users where id = {parameter}',
                    'statement_text' => 'select * from users where id = 2',
                )),
            ),
            array(
                'trace_id' => 'c',
                'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200),
                'sql' => array(array(
                    'operation' => 'SELECT',
                    'tables' => array('USERS'),
                    'statement_hash' => 'sha256:second',
                    'statement_normalized' => 'select * from users where email = {parameter}',
                    'statement_text' => "select * from users where email = 'coffee@example.com'",
                )),
            ),
        ));

        $entrypoint = $report['observed_entrypoints'][0];
        $this->assertCount(2, $entrypoint['patterns']);
        $this->assertSame(2, $entrypoint['patterns'][0]['count']);
        $this->assertSame('sha256:first', $entrypoint['patterns'][0]['sql_flow'][0]['statement_hash']);
        $this->assertSame('select * from users where id = {parameter}', $entrypoint['patterns'][0]['sql_flow'][0]['statement_normalized']);
    }

    public function testStatusCodeParticipatesInPattern()
    {
        $aggregator = new Aggregator();
        $sql = array(array(
            'operation' => 'SELECT',
            'tables' => array('USERS'),
            'statement_hash' => 'sha256:first',
            'statement_normalized' => 'select * from users',
        ));

        $report = $aggregator->aggregate(array(
            array('trace_id' => 'ok', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'sql' => $sql),
            array('trace_id' => 'error', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 500), 'sql' => $sql),
        ));

        $entrypoint = $report['observed_entrypoints'][0];
        $this->assertCount(2, $entrypoint['patterns']);
        $this->assertSame(array(200), $entrypoint['patterns'][0]['statuses']);
        $this->assertSame(array(500), $entrypoint['patterns'][1]['statuses']);
        $this->assertStringContainsString('STATUS:200', $entrypoint['patterns'][0]['observed_flow_signature']);
        $this->assertStringContainsString('STATUS:500', $entrypoint['patterns'][1]['observed_flow_signature']);
    }
}
