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
        $this->assertTrue($config->codeIgniter3CaptureQueryHistory());
        $this->assertSame('query_history', $config->codeIgniter3SqlCapture());
        $this->assertTrue($config->captureJsonRequestShape());
        $this->assertFalse($config->captureJsonResponseShape());
        $this->assertSame(65536, $config->maxBodyBytes());
    }

    public function testNormalizesLegacyCodeIgniter3QueryHistoryCapture()
    {
        $config = Config::fromArray(array(
            'codeigniter3' => array(
                'capture_query_history' => false,
            ),
        ));

        $this->assertFalse($config->codeIgniter3CaptureQueryHistory());
        $this->assertSame('none', $config->codeIgniter3SqlCapture());
    }

    public function testCanUseObservedDbSqlCaptureMode()
    {
        $config = Config::fromArray(array(
            'codeigniter3' => array(
                'sql_capture' => 'observed_db',
            ),
            'http' => array(
                'capture_json_request_shape' => false,
                'capture_json_response_shape' => true,
                'max_body_bytes' => 128,
            ),
        ));

        $this->assertSame('observed_db', $config->codeIgniter3SqlCapture());
        $this->assertFalse($config->captureJsonRequestShape());
        $this->assertTrue($config->captureJsonResponseShape());
        $this->assertSame(128, $config->maxBodyBytes());
    }
}
