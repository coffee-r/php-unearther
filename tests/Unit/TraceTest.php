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
}
