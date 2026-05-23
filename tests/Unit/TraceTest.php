<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Trace;
use PHPUnit\Framework\TestCase;

class TraceTest extends TestCase
{
    public function testGeneratedTraceIdHasTimestampAndLargeRandomSuffix()
    {
        $traceId = Trace::generateTraceId();

        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}-[a-f0-9]{32}$/', $traceId);
    }

    public function testSerializesTraceState()
    {
        $trace = new Trace('legacy-api', 'codeigniter3', true, 'trace-test');

        $trace->setHttp(array('method' => 'POST', 'path' => '/api/orders'));
        $trace->setHttp(array('status' => 201));
        $trace->addCall(array('type' => 'controller', 'class' => 'Orders', 'function' => 'create'));
        $trace->addSql(array('operation' => 'SELECT', 'tables' => array('ORDERS')));
        $trace->addSql(array('operation' => 'INSERT', 'tables' => array('ORDER_LOGS')));
        $trace->addExternalHttp(array('method' => 'POST', 'host' => 'payment.example.com', 'path' => '/authorize'));
        $trace->addError(array('type' => 'warning', 'message' => 'slow query'));

        $serialized = $trace->toArray();

        $this->assertSame(1, $serialized['schema_version']);
        $this->assertSame('trace-test', $serialized['trace_id']);
        $this->assertSame('legacy-api', $serialized['service']);
        $this->assertSame('codeigniter3', $serialized['framework']);
        $this->assertTrue($serialized['sampled']);
        $this->assertSame(array(
            'method' => 'POST',
            'path' => '/api/orders',
            'status' => 201,
        ), $serialized['http']);
        $this->assertSame(1, $serialized['calls'][0]['seq']);
        $this->assertSame('controller', $serialized['calls'][0]['type']);
        $this->assertSame(1, $serialized['sql'][0]['seq']);
        $this->assertSame(2, $serialized['sql'][1]['seq']);
        $this->assertSame('INSERT', $serialized['sql'][1]['operation']);
        $this->assertSame(1, $serialized['external_http'][0]['seq']);
        $this->assertSame('payment.example.com', $serialized['external_http'][0]['host']);
        $this->assertSame(array('type' => 'warning', 'message' => 'slow query'), $serialized['errors'][0]);
        $this->assertArrayHasKey('started_at', $serialized);
        $this->assertArrayNotHasKey('duration_ms', $serialized);
    }
}
