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
        $this->assertArrayNotHasKey('avg_duration_ms', $endpoint);
        $this->assertArrayNotHasKey('p95_duration_ms', $endpoint);
        $this->assertArrayNotHasKey('max_duration_ms', $endpoint);
    }

    public function testRepresentativeCaseIsAttachedToPattern()
    {
        $reader = new JsonlReader();
        $aggregator = new Aggregator();

        $report = $aggregator->aggregate($reader->read(array(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl')));
        $rep = $report['endpoints'][0]['patterns'][0]['representative'];

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
        $pattern = $report['endpoints'][0]['patterns'][0];

        $this->assertCount(1, $pattern['external_http']);
        $this->assertSame('payment.example.com', $pattern['external_http'][0]['host']);
    }

    public function testUsesCanonicalEndpointPathWhenPresent()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array('trace_id' => 'a', 'http' => array('method' => 'GET', 'path' => '/api/users/1', 'path_pattern' => '/api/users/{id}', 'status' => 200), 'sql' => array(), 'external_http' => array(), 'errors' => array()),
            array('trace_id' => 'b', 'http' => array('method' => 'GET', 'path' => '/api/users/2', 'path_pattern' => '/api/users/{id}', 'status' => 200), 'sql' => array(), 'external_http' => array(), 'errors' => array()),
        ));

        $this->assertSame(1, $report['endpoint_count']);
        $this->assertSame('/api/users/{id}', $report['endpoints'][0]['path']);
        $this->assertSame(2, $report['endpoints'][0]['observed_count']);
    }

    public function testAggregatesErrors()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array('trace_id' => 'a', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'errors' => array(array('type' => 'warning', 'message' => 'slow query'))),
            array('trace_id' => 'b', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 500), 'errors' => array(array('type' => 'warning', 'message' => 'slow query'))),
            array('trace_id' => 'c', 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'errors' => array()),
        ));
        $endpoint = $report['endpoints'][0];

        $this->assertSame(2, $endpoint['error_count']);
        $this->assertSame(0.6667, $endpoint['error_rate']);
        $this->assertSame('warning: slow query', $endpoint['errors'][0]['error']);
        $this->assertSame(2, $endpoint['errors'][0]['count']);
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

        $endpoint = $report['endpoints'][0];
        $this->assertCount(2, $endpoint['patterns']);
        $this->assertSame(2, $endpoint['patterns'][0]['count']);
        $this->assertSame('sha256:first', $endpoint['patterns'][0]['sql_flow'][0]['statement_hash']);
        $this->assertSame('select * from users where id = {parameter}', $endpoint['patterns'][0]['sql_flow'][0]['statement_normalized']);
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

        $endpoint = $report['endpoints'][0];
        $this->assertCount(2, $endpoint['patterns']);
        $this->assertSame(array(200), $endpoint['patterns'][0]['statuses']);
        $this->assertSame(array(500), $endpoint['patterns'][1]['statuses']);
        $this->assertStringContainsString('STATUS:200', $endpoint['patterns'][0]['signature']);
        $this->assertStringContainsString('STATUS:500', $endpoint['patterns'][1]['signature']);
    }
}
