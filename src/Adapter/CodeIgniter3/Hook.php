<?php

namespace CoffeeR\Unearther\Adapter\CodeIgniter3;

use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Config;
use CoffeeR\Unearther\Sampling\Sampler;
use CoffeeR\Unearther\Shape\ShapeExtractor;
use CoffeeR\Unearther\Sink\JsonlSink;

class Hook
{
    private static $collector;
    private static $shapeExtractor;
    private $config;

    public function __construct(array $config = array())
    {
        $this->config = Config::fromArray($this->normalizeConfig($config));
    }

    public function start(array $config = array())
    {
        $this->config = Config::fromArray(array_merge($this->config->toArray(), $this->normalizeConfig($config)));

        if (!$this->config->isEnabled()) {
            return;
        }

        self::$collector = new Collector(
            new Sampler($this->config->sampleRate()),
            new JsonlSink($this->config->sinkPath(), $this->config->sinkDateFormat())
        );
        self::$shapeExtractor = new ShapeExtractor();

        self::$collector->start($this->config->service(), $this->config->framework(), array(
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            'path' => isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '',
            'query_shape' => self::$shapeExtractor->extract($_GET),
            'request_shape' => self::$shapeExtractor->extract($_POST),
        ));
    }

    public function finish()
    {
        if (!self::$collector) {
            return;
        }

        self::$collector->finish(array(
            'status' => http_response_code(),
        ));
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
}
