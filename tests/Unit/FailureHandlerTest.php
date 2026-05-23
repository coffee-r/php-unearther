<?php

namespace {
    if (!function_exists('log_message')) {
        function log_message($level, $message)
        {
            $GLOBALS['__php_unearth_log_messages'][] = array($level, $message);
        }
    }
}

namespace CoffeeR\Unearth\Tests\Unit {
    use CoffeeR\Unearth\FailureHandler;
    use PHPUnit\Framework\TestCase;

    class FailureHandlerTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__php_unearth_log_messages'] = array();
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['__php_unearth_log_messages']);
        }

        public function testNormalizeModeAcceptsKnownAndFallsBackToThrow()
        {
            $this->assertSame(FailureHandler::MODE_THROW, FailureHandler::normalizeMode('throw'));
            $this->assertSame(FailureHandler::MODE_THROW, FailureHandler::normalizeMode('THROW'));
            $this->assertSame(FailureHandler::MODE_LOG, FailureHandler::normalizeMode('LOG'));
            $this->assertSame(FailureHandler::MODE_THROW, FailureHandler::normalizeMode('silent'));
            $this->assertSame(FailureHandler::MODE_THROW, FailureHandler::normalizeMode(null));
        }

        public function testModeGetterReflectsConstructorArgument()
        {
            $this->assertSame(FailureHandler::MODE_THROW, (new FailureHandler())->mode());
            $this->assertSame(FailureHandler::MODE_LOG, (new FailureHandler('log'))->mode());
            $this->assertSame(FailureHandler::MODE_THROW, (new FailureHandler('bogus'))->mode());
        }

        public function testHandleInThrowModeRethrowsOriginalException()
        {
            $handler = new FailureHandler('throw');
            $exception = new \RuntimeException('boom');

            try {
                $handler->handle($exception, 'sink write');
                $this->fail('Expected exception to be rethrown.');
            } catch (\RuntimeException $caught) {
                $this->assertSame($exception, $caught);
            }
        }

        public function testHandleInLogModeUsesCustomCallableLogger()
        {
            $messages = array();
            $handler = new FailureHandler('log', function ($message) use (&$messages) {
                $messages[] = $message;
            });

            $handler->handle(new \RuntimeException('boom'), 'sink write');

            $this->assertCount(1, $messages);
            $this->assertSame('[php-unearth] sink write failed: RuntimeException', $messages[0]);
        }

        public function testHandleInLogModeSwallowsLoggerThrowable()
        {
            $handler = new FailureHandler('log', function ($message) {
                throw new \RuntimeException('logger broke: ' . $message);
            });

            $handler->handle(new \RuntimeException('boom'), 'sink write');

            $this->assertTrue(true, 'logger that throws must not propagate from FailureHandler::handle');
        }

        public function testHandleInLogModeFallsBackToLogMessageFunction()
        {
            $handler = new FailureHandler('log');

            $handler->handle(new \LogicException('boom'), 'codeigniter3 hook start');

            $this->assertNotEmpty($GLOBALS['__php_unearth_log_messages']);
            $entry = $GLOBALS['__php_unearth_log_messages'][0];
            $this->assertSame('error', $entry[0]);
            $this->assertSame('[php-unearth] codeigniter3 hook start failed: LogicException', $entry[1]);
        }

        public function testNonCallableLoggerArgumentIsIgnored()
        {
            $handler = new FailureHandler('log', 'not a callable');

            $handler->handle(new \LogicException('boom'), 'sink write');

            $this->assertNotEmpty($GLOBALS['__php_unearth_log_messages']);
        }
    }
}
