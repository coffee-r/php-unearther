<?php

namespace CoffeeR\Unearther\Adapter\CodeIgniter3;

use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Config;
use CoffeeR\Unearther\FailureHandler;
use CoffeeR\Unearther\Http\EndpointPatternMatcher;
use CoffeeR\Unearther\Http\JsonBodyShapeExtractor;
use CoffeeR\Unearther\Sampling\Sampler;
use CoffeeR\Unearther\Shape\ShapeExtractor;
use CoffeeR\Unearther\Sink\JsonlSink;
use CoffeeR\Unearther\Sql\SqlAnalyzer;

class Hook
{
    private static $collector;
    private static $sharedConfig;
    private static $jsonShapeExtractor;
    private static $shapeExtractor;
    private static $endpointMatcher;
    private $config;

    public function __construct(array $config = array())
    {
        $this->config = Config::fromArray($this->normalizeConfig($config));
    }

    public function start(array $config = array())
    {
        try {
            return $this->startObserved($config);
        } catch (\Throwable $exception) {
            self::reset();
            $this->handleFailure($exception, 'codeigniter3 hook start');
            return null;
        }
    }

    private function startObserved(array $config = array())
    {
        $this->config = Config::fromArray($this->mergeConfig($this->config->toArray(), $this->normalizeConfig($config)));
        self::$sharedConfig = $this->config;

        if (!$this->config->isEnabled()) {
            self::reset();
            return;
        }

        self::$collector = new Collector(
            new Sampler($this->config->sampleRate()),
            new JsonlSink($this->config->sinkPath(), $this->config->sinkDateFormat()),
            $this->failureHandler()
        );
        self::$shapeExtractor = new ShapeExtractor();
        self::$jsonShapeExtractor = new JsonBodyShapeExtractor(self::$shapeExtractor);
        self::$endpointMatcher = new EndpointPatternMatcher();

        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $path = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        if ($path === false || $path === null) {
            $path = '';
        }
        $http = array(
            'method' => $method,
            'path' => $path,
            'path_pattern' => $path,
        );
        $endpoint = self::$endpointMatcher->match($method, $path, $this->config->endpointPatterns());
        if ($endpoint) {
            $http = array_merge($http, $endpoint);
        }

        $trace = self::$collector->start($this->config->service(), $this->config->framework(), $http);

        if ($trace->isSampled()) {
            $trace->setHttp(array(
                'query_shape' => self::$shapeExtractor->extract($_GET),
                'query_raw' => null,
                'request_shape' => $this->requestShape(),
                'request_raw' => null,
            ));
        }
    }

    public function finish(array $config = array())
    {
        try {
            return $this->finishObserved($config);
        } catch (\Throwable $exception) {
            $this->handleFailure($exception, 'codeigniter3 hook finish');
            return null;
        } finally {
            self::reset();
        }
    }

    private function finishObserved(array $config = array())
    {
        if (!self::$collector) {
            return;
        }

        if (self::$sharedConfig) {
            $this->config = self::$sharedConfig;
        }
        if (count($config) > 0) {
            $this->config = Config::fromArray($this->mergeConfig($this->config->toArray(), $this->normalizeConfig($config)));
            self::$sharedConfig = $this->config;
        }

        $this->recordCodeIgniterQueryHistory();

        $http = array(
            'status' => http_response_code(),
        );

        $trace = self::$collector->current();
        if ($trace && $trace->isSampled()) {
            $http['response_kind'] = $this->detectResponseKind();
            if ($this->config->captureJsonResponseShape()) {
                $responseShape = $this->responseShape();
                if ($responseShape !== null) {
                    $http['response_shape'] = $responseShape;
                }
            }
        }

        return self::$collector->finish($http);
    }

    public static function collector()
    {
        return self::$collector;
    }

    private function normalizeConfig(array $config)
    {
        if (!isset($config['framework'])) {
            $config['framework'] = 'codeigniter3';
        }

        if (isset($config['sink_path'])) {
            if (!isset($config['sink']) || !is_array($config['sink'])) {
                $config['sink'] = array();
            }
            $config['sink']['path'] = $config['sink_path'];
            unset($config['sink_path']);
        }

        return $config;
    }

    private static function reset()
    {
        self::$collector = null;
        self::$sharedConfig = null;
        self::$jsonShapeExtractor = null;
        self::$shapeExtractor = null;
        self::$endpointMatcher = null;
    }

    private function failureHandler()
    {
        return new FailureHandler($this->config->failureMode());
    }

    private function handleFailure(\Throwable $exception, $context)
    {
        $this->failureHandler()->handle($exception, $context);
    }

    private function mergeConfig(array $left, array $right)
    {
        foreach ($right as $key => $value) {
            if (isset($left[$key]) && is_array($left[$key]) && is_array($value) && !$this->isListArray($left[$key]) && !$this->isListArray($value)) {
                $left[$key] = $this->mergeConfig($left[$key], $value);
            } else {
                $left[$key] = $value;
            }
        }

        return $left;
    }

    private function isListArray(array $value)
    {
        if ($value === array()) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function recordCodeIgniterQueryHistory()
    {
        if ($this->config->codeIgniter3SqlCapture() !== 'query_history') {
            return;
        }

        $trace = self::$collector ? self::$collector->current() : null;
        if (!$trace || !$trace->isSampled()) {
            return;
        }

        if (!function_exists('get_instance')) {
            return;
        }

        $ci = get_instance();
        if (!isset($ci->db) || !isset($ci->db->queries) || !is_array($ci->db->queries)) {
            return;
        }

        $analyzer = new SqlAnalyzer($this->config->captureSqlText());
        foreach ($ci->db->queries as $sql) {
            self::$collector->addSql($analyzer->analyze($sql, array(), array(
                'source' => 'codeigniter3_query_history',
            )));
        }
    }

    private function requestShape()
    {
        if ($this->config->captureJsonRequestShape()) {
            $contentType = $this->requestContentType();
            if (self::$jsonShapeExtractor->isJsonContentType($contentType)) {
                $shape = self::$jsonShapeExtractor->extract(
                    $this->readInputBody($this->config->maxBodyBytes()),
                    $contentType,
                    $this->config->maxBodyBytes()
                );
                if ($shape !== null) {
                    return $shape;
                }
            }
        }

        return self::$shapeExtractor->extract($_POST);
    }

    private function responseShape()
    {
        if (!function_exists('get_instance')) {
            return null;
        }

        $ci = get_instance();
        if (!isset($ci->output) || !method_exists($ci->output, 'get_output')) {
            return null;
        }

        $contentType = $this->responseContentType($ci->output);
        if (!self::$jsonShapeExtractor || !self::$jsonShapeExtractor->isJsonContentType($contentType)) {
            return null;
        }

        $body = $ci->output->get_output();

        return self::$jsonShapeExtractor->extract($body, $contentType, $this->config->maxBodyBytes());
    }

    private function detectResponseKind()
    {
        $contentType = $this->currentResponseContentType();
        if ($contentType === '') {
            return 'other';
        }

        $lower = strtolower($contentType);
        if (strpos($lower, 'application/json') !== false || preg_match('#\+json(?:\b|$|;)#', $lower)) {
            return 'json';
        }
        if (strpos($lower, 'text/html') !== false || strpos($lower, 'application/xhtml') !== false) {
            return 'html';
        }

        return 'other';
    }

    private function currentResponseContentType()
    {
        if (function_exists('get_instance')) {
            $ci = get_instance();
            if (isset($ci->output) && method_exists($ci->output, 'get_content_type')) {
                $type = $ci->output->get_content_type();
                if ($type !== null && $type !== '') {
                    return $type;
                }
            }
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                return trim(substr($header, strlen('Content-Type:')));
            }
        }

        return '';
    }

    private function requestContentType()
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            return $_SERVER['HTTP_CONTENT_TYPE'];
        }

        return '';
    }

    private function responseContentType($output)
    {
        if (method_exists($output, 'get_content_type')) {
            return $output->get_content_type();
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                return trim(substr($header, strlen('Content-Type:')));
            }
        }

        return '';
    }

    private function readInputBody($maxBytes)
    {
        $handle = fopen('php://input', 'r');
        if (!$handle) {
            return '';
        }

        $body = stream_get_contents($handle, (int) $maxBytes + 1);
        fclose($handle);

        return $body === false ? '' : $body;
    }
}
