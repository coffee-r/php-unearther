<?php

namespace CoffeeR\Unearther\Guzzle;

use CoffeeR\Unearther\Collector;

class UneartherMiddleware
{
    public static function create(Collector $collector)
    {
        return function (callable $handler) use ($collector) {
            return function ($request, array $options) use ($handler, $collector) {
                $startedAt = microtime(true);

                try {
                    $promise = $handler($request, $options);
                } catch (\Throwable $exception) {
                    self::record($collector, $request, null, $startedAt, $exception);
                    throw $exception;
                }

                if (is_object($promise) && method_exists($promise, 'then')) {
                    return $promise->then(
                        function ($response) use ($collector, $request, $startedAt) {
                            self::record($collector, $request, $response, $startedAt, null);
                            return $response;
                        },
                        function ($reason) use ($collector, $request, $startedAt) {
                            $exception = $reason instanceof \Throwable ? $reason : null;
                            self::record($collector, $request, null, $startedAt, $exception);
                            if ($reason instanceof \Throwable) {
                                throw $reason;
                            }

                            throw new \RuntimeException('Guzzle request rejected.');
                        }
                    );
                }

                return $promise;
            };
        };
    }

    private static function record(Collector $collector, $request, $response, $startedAt, $exception)
    {
        $uri = method_exists($request, 'getUri') ? $request->getUri() : null;
        $method = method_exists($request, 'getMethod') ? $request->getMethod() : 'GET';
        $host = $uri && method_exists($uri, 'getHost') ? $uri->getHost() : '';
        $path = $uri && method_exists($uri, 'getPath') ? $uri->getPath() : '';
        $status = $response && method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;

        $event = array(
            'kind' => 'external_http',
            'method' => strtoupper($method),
            'host' => $host,
            'path' => $path,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'caller' => self::caller(),
        );

        if ($exception) {
            $event['error'] = get_class($exception) . ': ' . $exception->getMessage();
        }

        $collector->addExternalHttp($event);
    }

    private static function caller()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($trace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            if (strpos($frame['file'], 'UneartherMiddleware.php') !== false) {
                continue;
            }

            return array(
                'file' => $frame['file'],
                'line' => isset($frame['line']) ? $frame['line'] : null,
            );
        }

        return array();
    }
}
