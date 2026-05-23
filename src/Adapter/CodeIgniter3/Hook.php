<?php

namespace CoffeeR\Unearther\Adapter\CodeIgniter3;

use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Config;
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
    private $config;

    public function __construct(array $config = array())
    {
        $this->config = Config::fromArray($this->normalizeConfig($config));
    }

    public function start(array $config = array())
    {
        $this->config = Config::fromArray($this->mergeConfig($this->config->toArray(), $this->normalizeConfig($config)));
        self::$sharedConfig = $this->config;

        if (!$this->config->isEnabled()) {
            self::$collector = null;
            self::$jsonShapeExtractor = null;
            self::$shapeExtractor = null;
            return;
        }

        self::$collector = new Collector(
            new Sampler($this->config->sampleRate()),
            new JsonlSink($this->config->sinkPath(), $this->config->sinkDateFormat())
        );
        self::$shapeExtractor = new ShapeExtractor();
        self::$jsonShapeExtractor = new JsonBodyShapeExtractor(self::$shapeExtractor);

        $trace = self::$collector->start($this->config->service(), $this->config->framework(), array(
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            'path' => isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '',
        ));

        if ($trace->isSampled()) {
            $trace->setHttp(array(
                'query_shape' => self::$shapeExtractor->extract($_GET),
                'request_shape' => $this->requestShape(),
            ));
        }
    }

    public function finish(array $config = array())
    {
        if (!self::$collector) {
            self::$sharedConfig = null;
            self::$jsonShapeExtractor = null;
            self::$shapeExtractor = null;
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
        if ($trace && $trace->isSampled() && $this->config->captureJsonResponseShape()) {
            $responseShape = $this->responseShape();
            if ($responseShape !== null) {
                $http['response_shape'] = $responseShape;
            }
        }

        $finished = self::$collector->finish($http);
        self::$collector = null;
        self::$sharedConfig = null;
        self::$jsonShapeExtractor = null;
        self::$shapeExtractor = null;

        return $finished;
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

    private function mergeConfig(array $left, array $right)
    {
        foreach ($right as $key => $value) {
            if (isset($left[$key]) && is_array($left[$key]) && is_array($value)) {
                $left[$key] = $this->mergeConfig($left[$key], $value);
            } else {
                $left[$key] = $value;
            }
        }

        return $left;
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

        $analyzer = new SqlAnalyzer();
        foreach ($ci->db->queries as $index => $sql) {
            $duration = null;
            if (isset($ci->db->query_times[$index])) {
                $duration = (int) round($ci->db->query_times[$index] * 1000);
            }

            self::$collector->addSql($analyzer->analyze($sql, array(), array(
                'source' => 'codeigniter3_query_history',
            ), $duration));
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
