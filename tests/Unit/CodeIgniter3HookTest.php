<?php

namespace {
    if (!function_exists('get_instance')) {
        function get_instance()
        {
            return isset($GLOBALS['__php_unearther_ci_instance']) ? $GLOBALS['__php_unearther_ci_instance'] : null;
        }
    }
}

namespace CoffeeR\Unearther\Tests\Unit {
    use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;
    use PHPUnit\Framework\TestCase;

    class CodeIgniter3HookTest extends TestCase
    {
        private $paths = array();

        protected function setUp(): void
        {
            $_GET = array();
            $_POST = array();
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['REQUEST_URI'] = '/api/test?debug=1';
            unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE']);
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), null);
        }

        protected function tearDown(): void
        {
            if (Hook::collector()) {
                (new Hook())->finish(array('codeigniter3' => array('sql_capture' => 'none')));
            }

            foreach ($this->paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            unset($GLOBALS['__php_unearther_ci_instance']);
            $_GET = array();
            $_POST = array();
            unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE']);
        }

        public function testFinishUsesStartConfigAcrossHookInstances()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array('select * from users'), array(0.012), null);

            (new Hook())->start($this->config($path, array(
                'codeigniter3' => array('sql_capture' => 'none'),
            )));
            (new Hook())->finish();

        $trace = $this->readTrace($path);
        $this->assertSame(array(), $trace['sql']);
    }

    public function testFinishConfigCanOverrideSharedConfig()
    {
        $path = $this->tempPath();
        $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array('select * from users'), array(0.012), null);

        (new Hook())->start($this->config($path, array(
            'codeigniter3' => array('sql_capture' => 'query_history'),
        )));
        (new Hook())->finish(array(
            'codeigniter3' => array('sql_capture' => 'none'),
        ));

        $trace = $this->readTrace($path);
        $this->assertSame(array(), $trace['sql']);
    }

    public function testQueryHistoryCaptureRecordsSql()
    {
        $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array('select * from users'), array(0.012), null);

            (new Hook())->start($this->config($path, array(
                'codeigniter3' => array('sql_capture' => 'query_history'),
            )));
            (new Hook())->finish();

            $trace = $this->readTrace($path);
            $this->assertSame('SELECT', $trace['sql'][0]['operation']);
            $this->assertSame(array('USERS'), $trace['sql'][0]['tables']);
            $this->assertSame(12, $trace['sql'][0]['duration_ms']);
        }

        public function testObservedDbModeDoesNotAlsoRecordQueryHistory()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array('select * from users'), array(0.012), null);

            (new Hook())->start($this->config($path, array(
                'codeigniter3' => array('sql_capture' => 'observed_db'),
            )));
            (new Hook())->finish();

            $trace = $this->readTrace($path);
            $this->assertSame(array(), $trace['sql']);
        }

        public function testUnsampledTraceDoesNotCaptureShapesOrWrite()
        {
            $path = $this->tempPath();
            $_GET = array('secret' => 'query');
            $_POST = array('secret' => 'body');
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array('select * from users'), array(0.012), null);

            (new Hook())->start($this->config($path, array(
                'sample_rate' => 0.0,
                'codeigniter3' => array('sql_capture' => 'query_history'),
            )));

            $trace = Hook::collector()->current();
            $http = $trace->toArray()['http'];
            $this->assertFalse($trace->isSampled());
            $this->assertArrayNotHasKey('query_shape', $http);
            $this->assertArrayNotHasKey('request_shape', $http);

            (new Hook())->finish();
        $this->assertFileDoesNotExist($path);
    }

    public function testResponseShapeIsSkippedByDefault()
    {
        $path = $this->tempPath();
        $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), new CodeIgniter3OutputStub(
            'application/json',
            '{"ok":true}'
        ));

        (new Hook())->start($this->config($path));
        (new Hook())->finish();

        $trace = $this->readTrace($path);
        $this->assertArrayNotHasKey('response_shape', $trace['http']);
    }

    public function testResponseShapeIsCapturedOnlyWhenExplicitlyEnabled()
    {
        $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), new CodeIgniter3OutputStub(
                'application/json; charset=utf-8',
                '{"ok":true,"items":[{"id":1},{"name":"coffee"}]}'
            ));

            (new Hook())->start($this->config($path, array(
                'http' => array('capture_json_response_shape' => true),
            )));
            (new Hook())->finish();

            $trace = $this->readTrace($path);
            $this->assertSame(array(
                'ok' => 'boolean',
                'items' => array(array(
                    'id' => 'number',
                    'name' => 'string',
                )),
            ), $trace['http']['response_shape']);
        }

        private function config($path, array $overrides = array())
        {
            $config = array(
                'sample_rate' => 1.0,
                'sink' => array('path' => $path),
            );

            foreach ($overrides as $key => $value) {
                if (isset($config[$key]) && is_array($config[$key]) && is_array($value)) {
                    $config[$key] = array_merge($config[$key], $value);
                } else {
                    $config[$key] = $value;
                }
            }

            return $config;
        }

        private function ci(array $queries, array $queryTimes, $output)
        {
            $ci = new \stdClass();
            $ci->db = new \stdClass();
            $ci->db->queries = $queries;
            $ci->db->query_times = $queryTimes;
            if ($output) {
                $ci->output = $output;
            }

            return $ci;
        }

        private function tempPath()
        {
            $path = sys_get_temp_dir() . '/php-unearther-hook-test-' . uniqid('', true) . '.jsonl';
            $this->paths[] = $path;

            return $path;
        }

        private function readTrace($path)
        {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            return json_decode($lines[0], true);
        }
    }

    class CodeIgniter3OutputStub
    {
        private $contentType;
        private $output;

        public function __construct($contentType, $output)
        {
            $this->contentType = $contentType;
            $this->output = $output;
        }

        public function get_content_type()
        {
            return $this->contentType;
        }

        public function get_output()
        {
            return $this->output;
        }
    }
}
