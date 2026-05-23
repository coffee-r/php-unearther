<?php

namespace CoffeeR\Unearther;

class FailureHandler
{
    const MODE_THROW = 'throw';
    const MODE_LOG = 'log';

    private $mode;
    private $logger;

    public function __construct($mode = self::MODE_THROW, $logger = null)
    {
        $this->mode = self::normalizeMode($mode);
        $this->logger = is_callable($logger) ? $logger : null;
    }

    public static function normalizeMode($mode)
    {
        $mode = strtolower((string) $mode);
        if (in_array($mode, array(self::MODE_THROW, self::MODE_LOG), true)) {
            return $mode;
        }

        return self::MODE_THROW;
    }

    public function mode()
    {
        return $this->mode;
    }

    public function handle(\Throwable $exception, $context)
    {
        if ($this->mode === self::MODE_THROW) {
            throw $exception;
        }

        $this->log($this->formatMessage($exception, $context));
    }

    private function formatMessage(\Throwable $exception, $context)
    {
        return '[php-unearther] ' . (string) $context . ' failed: ' . get_class($exception);
    }

    private function log($message)
    {
        if ($this->logger) {
            try {
                call_user_func($this->logger, $message);
            } catch (\Throwable $exception) {
                @\error_log('[php-unearther] logger failed: ' . get_class($exception));
            }
            return;
        }

        if (function_exists('log_message')) {
            try {
                @\log_message('error', $message);
            } catch (\Throwable $exception) {
                @\error_log('[php-unearther] logger failed: ' . get_class($exception));
            }
            return;
        }

        @\error_log($message);
    }
}
