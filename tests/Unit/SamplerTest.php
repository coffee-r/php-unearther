<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Sampling\Sampler;
use PHPUnit\Framework\TestCase;

class SamplerTest extends TestCase
{
    public function testRateOneAlwaysSamples()
    {
        $sampler = new Sampler(1.0);

        $this->assertSame(1.0, $sampler->rate());
        for ($i = 0; $i < 16; $i++) {
            $this->assertTrue($sampler->shouldSample());
        }
    }

    public function testRateZeroNeverSamples()
    {
        $sampler = new Sampler(0.0);

        $this->assertSame(0.0, $sampler->rate());
        for ($i = 0; $i < 16; $i++) {
            $this->assertFalse($sampler->shouldSample());
        }
    }

    public function testClampsOutOfRangeRates()
    {
        $this->assertSame(0.0, (new Sampler(-0.25))->rate());
        $this->assertSame(1.0, (new Sampler(2.0))->rate());
        $this->assertSame(0.25, (new Sampler(0.25))->rate());
    }

    public function testFractionalRateReturnsBooleanWithoutThrowing()
    {
        $sampler = new Sampler(0.5);

        $sampled = 0;
        for ($i = 0; $i < 200; $i++) {
            if ($sampler->shouldSample()) {
                $sampled++;
            }
        }

        $this->assertGreaterThan(0, $sampled);
        $this->assertLessThan(200, $sampled);
    }
}
