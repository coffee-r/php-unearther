<?php

namespace CoffeeR\Unearther;

class Trace
{
    const SCHEMA_VERSION = 1;

    private $traceId;
    private $service;
    private $framework;
    private $sampled;
    private $startedAt;
    private $startedAtFloat;
    private $durationMs;
    private $http = array();
    private $calls = array();
    private $sql = array();
    private $externalHttp = array();
    private $errors = array();

    public function __construct($service, $framework, $sampled, $traceId = null)
    {
        $this->traceId = $traceId ?: self::generateTraceId();
        $this->service = $service;
        $this->framework = $framework;
        $this->sampled = (bool) $sampled;
        $this->startedAtFloat = microtime(true);
        $this->startedAt = date('c', (int) $this->startedAtFloat);
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

    public function addError(array $error)
    {
        $this->errors[] = $error;
    }

    public function markFinished()
    {
        if ($this->durationMs === null) {
            $this->durationMs = $this->elapsedDurationMs();
        }
    }

    public function toArray()
    {
        return array(
            'schema_version' => self::SCHEMA_VERSION,
            'trace_id' => $this->traceId,
            'service' => $this->service,
            'framework' => $this->framework,
            'sampled' => $this->sampled,
            'started_at' => $this->startedAt,
            'duration_ms' => $this->durationMs === null ? $this->elapsedDurationMs() : $this->durationMs,
            'http' => $this->http,
            'calls' => $this->calls,
            'sql' => $this->sql,
            'external_http' => $this->externalHttp,
            'errors' => $this->errors,
        );
    }

    private function elapsedDurationMs()
    {
        return (int) round((microtime(true) - $this->startedAtFloat) * 1000);
    }
}
