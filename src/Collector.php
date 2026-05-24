<?php

namespace CoffeeR\Unearth;

use CoffeeR\Unearth\Sampling\Sampler;
use CoffeeR\Unearth\Sink\SinkInterface;

class Collector
{
    private $sampler;
    private $sink;
    private $failureHandler;
    private $trace;
    private $environment;
    private $redaction;

    public function __construct(?Sampler $sampler = null, ?SinkInterface $sink = null, ?FailureHandler $failureHandler = null, $environment = 'production', array $redaction = array())
    {
        $this->sampler = $sampler ?: new Sampler(0.1);
        $this->sink = $sink;
        $this->failureHandler = $failureHandler ?: new FailureHandler();
        $this->environment = $environment;
        $this->redaction = $redaction;
    }

    public function start($service, $framework, array $http = array())
    {
        $this->trace = new Trace($service, $framework, $this->sampler->shouldSample(), null, $this->sampler->rate(), $this->environment, $this->redaction);
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

        if ($trace->isSampled() && $this->sink) {
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

    public function addView(array $view)
    {
        if ($this->trace && $this->trace->isSampled()) {
            $this->trace->addView($view);
        }
    }

    public function addError(array $error)
    {
        if ($this->trace && $this->trace->isSampled()) {
            $this->trace->addError($error);
        }
    }
}
