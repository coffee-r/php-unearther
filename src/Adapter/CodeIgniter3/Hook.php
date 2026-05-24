<?php

namespace CoffeeR\Unearth\Adapter\CodeIgniter3;

use CoffeeR\Unearth\Collector;
use CoffeeR\Unearth\Config;
use CoffeeR\Unearth\FailureHandler;
use CoffeeR\Unearth\Http\EndpointPatternMatcher;
use CoffeeR\Unearth\Http\JsonBodyShapeExtractor;
use CoffeeR\Unearth\Redaction\Redactor;
use CoffeeR\Unearth\Sampling\Sampler;
use CoffeeR\Unearth\Shape\ShapeExtractor;
use CoffeeR\Unearth\Sink\JsonlSink;
use CoffeeR\Unearth\Sql\SqlAnalyzer;

class Hook
{
    private static $collector;
    private static $sharedConfig;
    private static $jsonShapeExtractor;
    private static $shapeExtractor;
    private static $endpointMatcher;
    private static $redactor;
    private static $observedDbs = array();
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
            $this->failureHandler(),
            $this->config->environment(),
            $this->config->redactionMeta()
        );
        self::$redactor = new Redactor($this->config->redactionSecret(), $this->config->redactionTokenLength(), $this->config->redactionDenyKeys());
        self::$shapeExtractor = new ShapeExtractor($this->config->shapeMaxDepth(), $this->config->shapeMaxItems());
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
            'path_tokenized' => null,
            'path_raw' => null,
            'views' => array(),
        );
        $endpoint = self::$endpointMatcher->match($method, $path, $this->config->endpointPatterns());
        if ($endpoint) {
            $http = array_merge($http, $endpoint);
        }
        $http = array_merge($http, $this->routeInfo());
        $http['path_tokenized'] = $this->tokenizedPath($path, $http['path_pattern']);

        $trace = self::$collector->start($this->config->service(), $this->config->framework(), $http);
        $this->autoObserveDefaultDb();

        if ($trace->isSampled()) {
            $trace->setHttp(array(
                'query_shape' => self::$shapeExtractor->extract($_GET),
                'query_tokens' => self::$redactor ? self::$redactor->tokens($_GET) : null,
                'query_raw' => null,
                'request_shape' => $this->requestShape(),
                'request_tokens' => self::$redactor ? self::$redactor->tokens($this->requestValuesForTokens()) : null,
                'request_raw' => null,
            ));
        }
    }

    public function finish()
    {
        try {
            return $this->finishObserved();
        } catch (\Throwable $exception) {
            $this->handleFailure($exception, 'codeigniter3 hook finish');
            return null;
        } finally {
            self::reset();
        }
    }

    private function finishObserved()
    {
        if (!self::$collector) {
            return;
        }

        if (self::$sharedConfig) {
            $this->config = self::$sharedConfig;
        }

        $this->recordCodeIgniterQueryHistory();

        $http = array(
            'status' => http_response_code(),
        );

        $trace = self::$collector->current();
        if ($trace && $trace->isSampled()) {
            $http['content_type'] = $this->currentResponseContentType();
            $http['response_kind'] = $this->detectResponseKind();
            $http['response_bytes'] = $this->responseBytes();
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

    public static function observeDb($db, $name = 'default')
    {
        if (!is_object($db)) {
            return;
        }

        $key = spl_object_hash($db);
        if (!isset(self::$observedDbs[$key])) {
            self::$observedDbs[$key] = array(
                'db' => $db,
                'name' => (string) $name,
                'original_save_queries_exists' => property_exists($db, 'save_queries') || isset($db->save_queries),
                'original_save_queries' => isset($db->save_queries) ? $db->save_queries : null,
                'start_index' => isset($db->queries) && is_array($db->queries) ? count($db->queries) : 0,
            );
        }

        $trace = self::$collector ? self::$collector->current() : null;
        $config = self::$sharedConfig;
        if ($trace && $trace->isSampled() && $config && $config->codeIgniter3SqlCapture() === 'sampled_query_history') {
            $db->save_queries = true;
        }
    }

    public static function recordView($view, $vars = array())
    {
        $trace = self::$collector ? self::$collector->current() : null;
        if (!$trace || !$trace->isSampled() || !self::$shapeExtractor) {
            return;
        }

        $shape = self::$shapeExtractor->extract($vars);
        self::$collector->addView(array(
            'name' => (string) $view,
            'vars_shape' => $shape,
            'vars_tokens' => self::$redactor ? self::$redactor->tokens($vars) : null,
            'truncated' => self::shapeHasTruncation($shape),
        ));
    }

    private function normalizeConfig(array $config)
    {
        if (!isset($config['framework'])) {
            $config['framework'] = 'codeigniter3';
        }

        return $config;
    }

    private static function reset()
    {
        self::restoreObservedDbs();
        self::$collector = null;
        self::$sharedConfig = null;
        self::$jsonShapeExtractor = null;
        self::$shapeExtractor = null;
        self::$endpointMatcher = null;
        self::$redactor = null;
        self::$observedDbs = array();
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
        if ($this->config->codeIgniter3SqlCapture() !== 'sampled_query_history') {
            return;
        }

        $trace = self::$collector ? self::$collector->current() : null;
        if (!$trace || !$trace->isSampled()) {
            return;
        }

        $this->autoObserveDefaultDb();
        if (count(self::$observedDbs) === 0) {
            self::$collector->addError(array(
                'type' => 'warning',
                'message' => 'codeigniter3 db object was not observed; SQL query history was not captured',
            ));
            return;
        }

        $analyzer = new SqlAnalyzer($this->config->captureSqlText(), self::$redactor, $this->config->captureBindRaw());
        foreach (self::$observedDbs as $entry) {
            $db = $entry['db'];
            if (!isset($db->queries) || !is_array($db->queries)) {
                self::$collector->addError(array(
                    'type' => 'warning',
                    'message' => 'observed db has no query history: ' . $entry['name'],
                ));
                continue;
            }

            $queries = array_slice($db->queries, (int) $entry['start_index']);
            foreach ($queries as $sql) {
                self::$collector->addSql($analyzer->analyze($sql, array(), 'codeigniter3_query_history'));
            }
        }
    }

    private function autoObserveDefaultDb()
    {
        if (!function_exists('get_instance')) {
            return;
        }

        $ci = get_instance();
        if ($ci && isset($ci->db) && is_object($ci->db)) {
            self::observeDb($ci->db, 'default');
        }
    }

    private static function restoreObservedDbs()
    {
        foreach (self::$observedDbs as $entry) {
            $db = $entry['db'];
            if (!is_object($db)) {
                continue;
            }
            if ($entry['original_save_queries_exists']) {
                $db->save_queries = $entry['original_save_queries'];
            } elseif (isset($db->save_queries)) {
                unset($db->save_queries);
            }
        }
    }

    private static function shapeHasTruncation($shape)
    {
        if ($shape === 'truncated_depth' || $shape === 'recursive') {
            return true;
        }
        if (!is_array($shape)) {
            return false;
        }
        foreach ($shape as $key => $value) {
            if ($key === '__truncated__' || self::shapeHasTruncation($value)) {
                return true;
            }
        }

        return false;
    }

    private function tokenizedPath($path, $pattern)
    {
        if (!self::$redactor || !self::$redactor->hasSecret()) {
            return null;
        }

        $pathSegments = $this->pathSegments($path);
        $patternSegments = $this->pathSegments($pattern);
        $out = array();
        foreach ($pathSegments as $index => $segment) {
            $patternSegment = isset($patternSegments[$index]) ? $patternSegments[$index] : null;
            if ($patternSegment && preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*\}$/', $patternSegment)) {
                $out[] = self::$redactor->token($segment);
            } elseif (preg_match('/^\d+$|^[a-f0-9-]{16,}$/i', $segment)) {
                $out[] = self::$redactor->token($segment);
            } else {
                $out[] = $segment;
            }
        }

        return '/' . implode('/', $out);
    }

    private function routeInfo()
    {
        if (!function_exists('get_instance')) {
            return array();
        }

        $ci = get_instance();
        if (!$ci || !isset($ci->router)) {
            return array();
        }

        $class = method_exists($ci->router, 'fetch_class') ? $ci->router->fetch_class() : null;
        $method = method_exists($ci->router, 'fetch_method') ? $ci->router->fetch_method() : null;
        $directory = method_exists($ci->router, 'fetch_directory') ? $ci->router->fetch_directory() : '';
        $info = array();
        if ($class !== null && $class !== '') {
            $info['controller'] = $class;
        }
        if ($method !== null && $method !== '') {
            $info['action'] = $method;
        }
        if (isset($info['controller']) && isset($info['action'])) {
            $info['route'] = $info['controller'] . '/' . $info['action'];
        }
        if (isset($info['controller'])) {
            $info['controller_path'] = $this->controllerPath($directory, $info['controller']);
        }

        return $info;
    }

    private function controllerPath($directory, $controller)
    {
        $directory = trim(str_replace('\\', '/', (string) $directory), '/');
        $controllerFile = ucfirst((string) $controller) . '.php';

        return 'application/controllers/' . ($directory === '' ? '' : $directory . '/') . $controllerFile;
    }

    private function pathSegments($path)
    {
        $path = trim((string) $path, '/');
        if ($path === '') {
            return array();
        }

        return explode('/', $path);
    }

    private function requestValuesForTokens()
    {
        if ($this->config->captureJsonRequestShape()) {
            $contentType = $this->requestContentType();
            if (self::$jsonShapeExtractor->isJsonContentType($contentType)) {
                $body = $this->readInputBody($this->config->maxBodyBytes());
                if (strlen($body) <= $this->config->maxBodyBytes()) {
                    $decoded = json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        }

        return $_POST;
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

    private function responseBytes()
    {
        if (!function_exists('get_instance')) {
            return null;
        }

        $ci = get_instance();
        if (!isset($ci->output) || !method_exists($ci->output, 'get_output')) {
            return null;
        }

        $body = $ci->output->get_output();
        if (!is_string($body)) {
            return null;
        }

        return strlen($body);
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
