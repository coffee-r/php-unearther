<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testDefaultsCanBeOverridden()
    {
        $config = Config::fromArray(array(
            'service' => 'shop-api',
            'framework' => 'codeigniter3',
            'sample_rate' => 0.25,
            'sink' => array(
                'path' => '/tmp/unearther-{date}.jsonl',
            ),
        ));

        $this->assertTrue($config->isEnabled());
        $this->assertSame('shop-api', $config->service());
        $this->assertSame('codeigniter3', $config->framework());
        $this->assertSame(0.25, $config->sampleRate());
        $this->assertSame('jsonl', $config->sinkType());
        $this->assertSame('/tmp/unearther-{date}.jsonl', $config->sinkPath());
        $this->assertSame('Y-m-d', $config->sinkDateFormat());
    }
}
