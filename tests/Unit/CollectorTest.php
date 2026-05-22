<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Sampling\Sampler;
use CoffeeR\Unearther\Sink\SinkInterface;
use PHPUnit\Framework\TestCase;

class CollectorTest extends TestCase
{
    public function testWritesSampledTraceOnFinish()
    {
        $sink = new MemorySink();
        $collector = new Collector(new Sampler(1.0), $sink);

        $collector->start('legacy-api', 'codeigniter3', array('method' => 'GET', 'path' => '/ping'));
        $collector->addSql(array('operation' => 'SELECT', 'tables' => array('DUAL')));
        $collector->finish(array('status' => 200));

        $this->assertCount(1, $sink->traces);
        $this->assertSame('/ping', $sink->traces[0]['http']['path']);
        $this->assertSame('SELECT', $sink->traces[0]['sql'][0]['operation']);
    }

    public function testDoesNotWriteUnsampledTrace()
    {
        $sink = new MemorySink();
        $collector = new Collector(new Sampler(0.0), $sink);

        $collector->start('legacy-api', 'codeigniter3');
        $collector->finish();

        $this->assertCount(0, $sink->traces);
    }
}

class MemorySink implements SinkInterface
{
    public $traces = array();

    public function write(array $trace)
    {
        $this->traces[] = $trace;
    }
}
