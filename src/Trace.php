<?php

namespace CoffeeR\Unearther;

class Trace
{
    const SCHEMA_VERSION = 1;

    private $traceId;
    private $service;
    private $framework;
    private $environment;
    private $sampled;
    private $sampleRate;
    private $startedAt;
    private $redaction;
    private $http = array();
    private $calls = array();
    private $sql = array();
    private $externalHttp = array();
    private $errors = array();

    public function __construct($service, $framework, $sampled, $traceId = null, $sampleRate = 1.0, $environment = 'production', array $redaction = array())
    {
        $this->traceId = $traceId ?: self::generateTraceId();
        $this->service = $service;
        $this->framework = $framework;
        $this->environment = $environment;
        $this->sampled = (bool) $sampled;
        $this->sampleRate = (float) $sampleRate;
        $this->startedAt = date('c');
        $this->redaction = array_merge(array(
            'tokenized' => false,
            'token_format' => null,
        ), $redaction);
    }

    public static function generateTraceId()
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Exception $exception) {
            $random = str_replace('.', '', uniqid('', true));
        }

        return gmdate('Ymd\THis') . '-' . $random;
    }

    public function isSampled()
    {
        return $this->sampled;
    }

    public function getTraceId()
    {
        return $this->traceId;
    }

    public function setHttp(array $http)
    {
        $this->http = array_merge($this->http, $http);
    }

    public function addCall(array $call)
    {
        $call['seq'] = count($this->calls) + 1;
        $this->calls[] = $call;
    }

    public function addSql(array $sql)
    {
        $sql['seq'] = count($this->sql) + 1;
        $this->sql[] = $sql;
    }

    public function addExternalHttp(array $event)
    {
        $event['seq'] = count($this->externalHttp) + 1;
        $this->externalHttp[] = $event;
    }

    public function addView(array $view)
    {
        if (!isset($this->http['views']) || !is_array($this->http['views'])) {
            $this->http['views'] = array();
        }

        $view['seq'] = count($this->http['views']) + 1;
        $this->http['views'][] = $view;
    }

    public function addError(array $error)
    {
        $this->errors[] = $error;
    }

    public function toArray()
    {
        return array(
            'schema_version' => self::SCHEMA_VERSION,
            'trace_id' => $this->traceId,
            'service' => $this->service,
            'framework' => $this->framework,
            'environment' => $this->environment,
            'sampled' => $this->sampled,
            'sample_rate' => $this->sampleRate,
            'started_at' => $this->startedAt,
            'redaction' => $this->redaction,
            'http' => $this->http,
            'calls' => $this->calls,
            'sql' => $this->sql,
            'external_http' => $this->externalHttp,
            'errors' => $this->errors,
        );
    }
}
