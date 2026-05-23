<?php

namespace {
    if (!function_exists('get_instance')) {
        function get_instance()
        {
            return isset($GLOBALS['__php_unearther_ci_instance']) ? $GLOBALS['__php_unearther_ci_instance'] : null;
        }
    }
    if (!function_exists('log_message')) {
        function log_message($level, $message)
        {
            $GLOBALS['__php_unearther_log_messages'][] = array($level, $message);
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
            $GLOBALS['__php_unearther_log_messages'] = array();
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
            unset($GLOBALS['__php_unearther_log_messages']);
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
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), null);
            $GLOBALS['__php_unearther_ci_instance']->db->save_queries = false;

            (new Hook())->start($this->config($path, array(
                'codeigniter3' => array('sql_capture' => 'sampled_query_history'),
            )));
            $this->assertTrue($GLOBALS['__php_unearther_ci_instance']->db->save_queries);
            $GLOBALS['__php_unearther_ci_instance']->db->queries[] = 'select * from users';
            (new Hook())->finish();

            $trace = $this->readTrace($path);
            $this->assertSame('SELECT', $trace['sql'][0]['operation']);
            $this->assertSame(array('USERS'), $trace['sql'][0]['tables']);
            $this->assertArrayNotHasKey('duration_ms', $trace['sql'][0]);
            $this->assertNull($trace['sql'][0]['statement_text']);
            $this->assertSame('codeigniter3_query_history', $trace['sql'][0]['caller']['source']);
            $this->assertContains('query_history_capture_has_no_precise_caller_or_bind_values', $trace['sql'][0]['analysis']['warnings']);
            $this->assertSame('select * from users', $trace['sql'][0]['statement_normalized']);
            $this->assertFalse($GLOBALS['__php_unearther_ci_instance']->db->save_queries);
        }

        public function testQueryHistoryCaptureCanRecordSqlText()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), null);

            (new Hook())->start($this->config($path, array(
                'codeigniter3' => array('sql_capture' => 'sampled_query_history'),
                'sql' => array('capture_text' => true),
            )));
            $GLOBALS['__php_unearther_ci_instance']->db->queries[] = " select * from users where id = 42 and name = 'coffee' ";
            (new Hook())->finish();

            $sql = $this->readTrace($path)['sql'][0];
            $this->assertSame(" select * from users where id = 42 and name = 'coffee' ", $sql['statement_text']);
            $this->assertSame('select * from users where id = {parameter} and name = {parameter}', $sql['statement_normalized']);
            $this->assertStringStartsWith('sha256:', $sql['statement_hash']);
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

        public function testUnsampledRequestDoesNotEnableSaveQueries()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), null);
            $GLOBALS['__php_unearther_ci_instance']->db->save_queries = false;

            (new Hook())->start($this->config($path, array(
                'sample_rate' => 0.0,
                'codeigniter3' => array('sql_capture' => 'sampled_query_history'),
            )));

            $this->assertFalse($GLOBALS['__php_unearther_ci_instance']->db->save_queries);
            (new Hook())->finish();
        }

        public function testObserveDbCapturesLaterLoadedConnection()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), null);
            unset($GLOBALS['__php_unearther_ci_instance']->db);

            (new Hook())->start($this->config($path));
            $db = new \stdClass();
            $db->queries = array();
            $db->save_queries = false;
            Hook::observeDb($db, 'reporting');
            $this->assertTrue($db->save_queries);
            $db->queries[] = 'select * from reports';
            (new Hook())->finish();

            $trace = $this->readTrace($path);
            $this->assertSame('reporting', $trace['sql'][0]['caller']['db']);
            $this->assertFalse($db->save_queries);
        }

        public function testRecordViewCapturesShapeWithoutRawValues()
        {
            $path = $this->tempPath();

            (new Hook())->start($this->config($path, array(
                'redaction' => array('secret' => 'test-secret'),
            )));
            Hook::recordView('orders/detail', array(
                'order' => array('id' => 123, 'secret_token' => 'hidden'),
            ));
            (new Hook())->finish();

            $view = $this->readTrace($path)['http']['views'][0];
            $this->assertSame('orders/detail', $view['name']);
            $this->assertSame('number', $view['vars_shape']['order']['id']);
            $this->assertSame('{redacted}', $view['vars_tokens']['order']['secret_token']);
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

        public function testEndpointPatternIsRecordedWhenConfigured()
        {
            $path = $this->tempPath();
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/users/123?debug=1';

            (new Hook())->start($this->config($path, array(
                'http' => array(
                    'endpoint_patterns' => array(
                        array('method' => 'GET', 'path' => '/api/users/{id}', 'name' => 'users.show'),
                    ),
                ),
            )));
            (new Hook())->finish();

            $trace = $this->readTrace($path);
            $this->assertSame('/api/users/123', $trace['http']['path']);
            $this->assertSame('/api/users/{id}', $trace['http']['path_pattern']);
            $this->assertSame('users.show', $trace['http']['endpoint_name']);
        }

        public function testEndpointPatternOverridesReplacePreviousLists()
        {
            $path = $this->tempPath();
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/api/legacy/123';

            $hook = new Hook($this->config($path, array(
                'http' => array(
                    'endpoint_patterns' => array(
                        array('method' => 'GET', 'path' => '/api/legacy/{id}', 'name' => 'legacy.show'),
                    ),
                ),
            )));
            $hook->start(array(
                'http' => array(
                    'endpoint_patterns' => array(
                        array('method' => 'GET', 'path' => '/api/orders/{id}', 'name' => 'orders.show'),
                    ),
                ),
            ));
            $hook->finish();

            $trace = $this->readTrace($path);
            $this->assertSame('/api/legacy/123', $trace['http']['path']);
            $this->assertSame('/api/legacy/123', $trace['http']['path_pattern']);
            $this->assertArrayNotHasKey('endpoint_name', $trace['http']);
        }

        public function testFinishThrowsObservationFailuresByDefaultAndResetsState()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), new ThrowingCodeIgniter3OutputStub());

            (new Hook())->start($this->config($path, array(
                'http' => array('capture_json_response_shape' => true),
            )));

            try {
                (new Hook())->finish();
                $this->fail('Expected hook finish to throw observation failure.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('output unavailable', $exception->getMessage());
            }

            $this->assertNull(Hook::collector());
        }

        public function testFinishLogsObservationFailuresInLogModeAndResetsState()
        {
            $path = $this->tempPath();
            $GLOBALS['__php_unearther_ci_instance'] = $this->ci(array(), array(), new ThrowingCodeIgniter3OutputStub());

            (new Hook())->start($this->config($path, array(
                'failure_mode' => 'log',
                'http' => array('capture_json_response_shape' => true),
            )));
            $this->assertNull((new Hook())->finish());

            $this->assertNull(Hook::collector());
            $this->assertFileDoesNotExist($path);
            $this->assertSame('error', $GLOBALS['__php_unearther_log_messages'][0][0]);
            $this->assertStringContainsString('[php-unearther] codeigniter3 hook finish failed: RuntimeException', $GLOBALS['__php_unearther_log_messages'][0][1]);
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

    class ThrowingCodeIgniter3OutputStub
    {
        public function get_content_type()
        {
            return 'application/json';
        }

        public function get_output()
        {
            throw new \RuntimeException('output unavailable');
        }
    }
}
