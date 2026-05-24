<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit;

use CoffeeR\Ci3Unearth\Collector;
use CoffeeR\Ci3Unearth\FailureHandler;
use CoffeeR\Ci3Unearth\Sampling\Sampler;
use CoffeeR\Ci3Unearth\Sink\SinkInterface;
use PHPUnit\Framework\TestCase;

class CollectorTest extends TestCase
{
    public function testDefaultSamplerUsesTenPercentRate()
    {
        $collector = new Collector();
        $trace = $collector->start('legacy-api', 'codeigniter3');

        $this->assertSame(0.1, $trace->toArray()['sample_rate']);
    }

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

    public function testThrowsSinkFailuresWhenConfiguredToThrow()
    {
        $collector = new Collector(new Sampler(1.0), new ThrowingSink(), new FailureHandler('throw'));
        $collector->start('legacy-api', 'codeigniter3');

        $this->expectException(\RuntimeException::class);
        $collector->finish();
    }

    public function testLogsSinkFailuresInLogMode()
    {
        $messages = array();
        $logger = function ($message) use (&$messages) {
            $messages[] = $message;
        };
        $collector = new Collector(
            new Sampler(1.0),
            new ThrowingSink(),
            new FailureHandler('log', $logger)
        );

        $collector->start('legacy-api', 'codeigniter3');
        $trace = $collector->finish();

        $this->assertNotNull($trace);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('[php-ci3-unearth] sink write failed: RuntimeException', $messages[0]);
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

class ThrowingSink implements SinkInterface
{
    public function write(array $trace)
    {
        throw new \RuntimeException('sink unavailable with possible details');
    }
}
