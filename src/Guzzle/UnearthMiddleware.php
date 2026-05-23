<?php

namespace CoffeeR\Unearth\Guzzle;

use CoffeeR\Unearth\Collector;

class UnearthMiddleware
{
    public static function create(Collector $collector)
    {
        return function (callable $handler) use ($collector) {
            return function ($request, array $options) use ($handler, $collector) {
                $caller = self::caller();

                try {
                    $promise = $handler($request, $options);
                } catch (\Throwable $exception) {
                    self::record($collector, $request, null, $exception, $caller);
                    throw $exception;
                }

                if (is_object($promise) && method_exists($promise, 'then')) {
                    return $promise->then(
                        function ($response) use ($collector, $request, $caller) {
                            self::record($collector, $request, $response, null, $caller);
                            return $response;
                        },
                        function ($reason) use ($collector, $request, $caller) {
                            $exception = $reason instanceof \Throwable ? $reason : null;
                            self::record($collector, $request, null, $exception, $caller);
                            if ($reason instanceof \Throwable) {
                                throw $reason;
                            }

                            throw new \RuntimeException('Guzzle request rejected.');
                        }
                    );
                }

                self::record($collector, $request, $promise, null, $caller);

                return $promise;
            };
        };
    }

    private static function record(Collector $collector, $request, $response, $exception, array $caller)
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
            'caller' => $caller,
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
            if (strpos($frame['file'], 'UnearthMiddleware.php') !== false) {
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
