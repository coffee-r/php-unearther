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

    public function testUsesCanonicalEndpointPathWhenPresent()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array('trace_id' => 'a', 'duration_ms' => 1, 'http' => array('method' => 'GET', 'path' => '/api/users/1', 'endpoint_path' => '/api/users/{id}', 'status' => 200), 'sql' => array(), 'external_http' => array(), 'errors' => array()),
            array('trace_id' => 'b', 'duration_ms' => 1, 'http' => array('method' => 'GET', 'path' => '/api/users/2', 'endpoint_path' => '/api/users/{id}', 'status' => 200), 'sql' => array(), 'external_http' => array(), 'errors' => array()),
        ));

        $this->assertSame(1, $report['endpoint_count']);
        $this->assertSame('/api/users/{id}', $report['endpoints'][0]['path']);
        $this->assertSame(2, $report['endpoints'][0]['observed_count']);
    }

    public function testAggregatesErrors()
    {
        $aggregator = new Aggregator();
        $report = $aggregator->aggregate(array(
            array('trace_id' => 'a', 'duration_ms' => 1, 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'errors' => array(array('type' => 'warning', 'message' => 'slow query'))),
            array('trace_id' => 'b', 'duration_ms' => 1, 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 500), 'errors' => array(array('type' => 'warning', 'message' => 'slow query'))),
            array('trace_id' => 'c', 'duration_ms' => 1, 'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200), 'errors' => array()),
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
                'duration_ms' => 1,
                'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200),
                'sql' => array(array(
                    'operation' => 'SELECT',
                    'tables' => array('USERS'),
                    'statement_hash' => 'sha256:first',
                    'fingerprint_sql' => 'select * from users where id = ?',
                    'raw_sql' => 'select * from users where id = 1',
                )),
            ),
            array(
                'trace_id' => 'b',
                'duration_ms' => 1,
                'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200),
                'sql' => array(array(
                    'operation' => 'SELECT',
                    'tables' => array('USERS'),
                    'statement_hash' => 'sha256:first',
                    'fingerprint_sql' => 'select * from users where id = ?',
                    'raw_sql' => 'select * from users where id = 2',
                )),
            ),
            array(
                'trace_id' => 'c',
                'duration_ms' => 1,
                'http' => array('method' => 'GET', 'path' => '/api/users', 'status' => 200),
                'sql' => array(array(
                    'operation' => 'SELECT',
                    'tables' => array('USERS'),
                    'statement_hash' => 'sha256:second',
                    'fingerprint_sql' => 'select * from users where email = ?',
                    'raw_sql' => "select * from users where email = 'coffee@example.com'",
                )),
            ),
        ));

        $endpoint = $report['endpoints'][0];
        $this->assertCount(2, $endpoint['patterns']);
        $this->assertSame(2, $endpoint['patterns'][0]['count']);
        $this->assertSame('sha256:first', $endpoint['patterns'][0]['sql_flow'][0]['statement_hash']);
        $this->assertSame('select * from users where id = ?', $endpoint['patterns'][0]['sql_flow'][0]['fingerprint_sql']);
        $this->assertStringNotContainsString('coffee@example.com', json_encode($report));
    }
}
