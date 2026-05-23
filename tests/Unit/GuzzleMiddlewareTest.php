<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Guzzle\UneartherMiddleware;
use CoffeeR\Unearther\Sampling\Sampler;
use CoffeeR\Unearther\Sink\SinkInterface;
use PHPUnit\Framework\TestCase;

class GuzzleMiddlewareTest extends TestCase
{
    public function testRecordsSynchronousResponse()
    {
        $sink = new GuzzleMemorySink();
        $collector = new Collector(new Sampler(1.0), $sink);
        $collector->start('legacy-api', 'codeigniter3');

        $middleware = UneartherMiddleware::create($collector);
        $handler = $middleware(function ($request, array $options) {
            return new GuzzleResponseStub(201);
        });

        $response = $handler(new GuzzleRequestStub('POST', 'api.example.com', '/sync'), array());
        $collector->finish();

        $this->assertSame(201, $response->getStatusCode());
        $event = $sink->traces[0]['external_http'][0];
        $this->assertSame('POST', $event['method']);
        $this->assertSame('api.example.com', $event['host']);
        $this->assertSame('/sync', $event['path']);
        $this->assertSame(201, $event['status']);
        $this->assertSame('GuzzleMiddlewareTest.php', basename($event['caller']['file']));
    }

    public function testRecordsPromiseResponse()
    {
        $sink = new GuzzleMemorySink();
        $collector = new Collector(new Sampler(1.0), $sink);
        $collector->start('legacy-api', 'codeigniter3');

        $middleware = UneartherMiddleware::create($collector);
        $handler = $middleware(function ($request, array $options) {
            return new GuzzleFulfilledPromiseStub(new GuzzleResponseStub(202));
        });

        $response = $handler(new GuzzleRequestStub('GET', 'api.example.com', '/promise'), array());
        $collector->finish();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('/promise', $sink->traces[0]['external_http'][0]['path']);
    }

    public function testRecordsRejectedPromise()
    {
        $sink = new GuzzleMemorySink();
        $collector = new Collector(new Sampler(1.0), $sink);
        $collector->start('legacy-api', 'codeigniter3');

        $middleware = UneartherMiddleware::create($collector);
        $handler = $middleware(function ($request, array $options) {
            return new GuzzleRejectedPromiseStub(new \RuntimeException('network failed'));
        });

        try {
            $handler(new GuzzleRequestStub('GET', 'api.example.com', '/rejected'), array());
            $this->fail('Expected rejected promise to throw.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('network failed', $exception->getMessage());
        }

        $collector->finish();
        $event = $sink->traces[0]['external_http'][0];
        $this->assertSame('/rejected', $event['path']);
        $this->assertStringContainsString('RuntimeException: network failed', $event['error']);
    }
}

class GuzzleRequestStub
{
    private $method;
    private $uri;

    public function __construct($method, $host, $path)
    {
        $this->method = $method;
        $this->uri = new GuzzleUriStub($host, $path);
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUri()
    {
        return $this->uri;
    }
}

class GuzzleUriStub
{
    private $host;
    private $path;

    public function __construct($host, $path)
    {
        $this->host = $host;
        $this->path = $path;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPath()
    {
        return $this->path;
    }
}

class GuzzleResponseStub
{
    private $status;

    public function __construct($status)
    {
        $this->status = $status;
    }

    public function getStatusCode()
    {
        return $this->status;
    }
}

class GuzzleFulfilledPromiseStub
{
    private $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function then($onFulfilled, $onRejected = null)
    {
        return $onFulfilled($this->response);
    }
}

class GuzzleRejectedPromiseStub
{
    private $reason;

    public function __construct($reason)
    {
        $this->reason = $reason;
    }

    public function then($onFulfilled, $onRejected = null)
    {
        return $onRejected ? $onRejected($this->reason) : null;
    }
}

class GuzzleMemorySink implements SinkInterface
{
    public $traces = array();

    public function write(array $trace)
    {
        $this->traces[] = $trace;
    }
}
