<?php

namespace CoffeeR\Unearther;

use CoffeeR\Unearther\Sampling\Sampler;
use CoffeeR\Unearther\Sink\SinkInterface;
use CoffeeR\Unearther\Sink\NullSink;

class Collector
{
    private $sampler;
    private $sink;
    private $failureHandler;
    private $trace;

    public function __construct(?Sampler $sampler = null, ?SinkInterface $sink = null, ?FailureHandler $failureHandler = null)
    {
        $this->sampler = $sampler ?: new Sampler(1.0);
        $this->sink = $sink ?: new NullSink();
        $this->failureHandler = $failureHandler ?: new FailureHandler();
    }

    public function start($service, $framework, array $http = array())
    {
        $this->trace = new Trace($service, $framework, $this->sampler->shouldSample());
        $this->trace->setHttp($http);

        return $this->trace;
    }

    public function current()
    {
        return $this->trace;
    }

    public function finish(array $http = array())
    {
        if (!$this->trace) {
            return null;
        }

        $this->trace->setHttp($http);
        $trace = $this->trace;
        $this->trace = null;

        if ($trace->isSampled()) {
            try {
                $this->sink->write($trace->toArray());
            } catch (\Throwable $exception) {
                $this->failureHandler->handle($exception, 'sink write');
            }
        }

        return $trace;
    }

    public function addSql(array $sql)
    {
        if ($this->trace && $this->trace->isSampled()) {
            $this->trace->addSql($sql);
        }
    }

    public function addExternalHttp(array $event)
    {
        if ($this->trace && $this->trace->isSampled()) {
            $this->trace->addExternalHttp($event);
        }
    }

    public function addError(array $error)
    {
        if ($this->trace && $this->trace->isSampled()) {
            $this->trace->addError($error);
        }
    }
}
