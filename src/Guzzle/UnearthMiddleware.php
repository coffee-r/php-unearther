<?php

namespace CoffeeR\Ci3Unearth\Guzzle;

use CoffeeR\Ci3Unearth\Collector;

class UnearthMiddleware
{
    public static function create(Collector $collector)
    {
        return function (callable $handler) use ($collector) {
            return function ($request, array $options) use ($handler, $collector) {
                try {
                    $promise = $handler($request, $options);
                } catch (\Throwable $exception) {
                    self::record($collector, $request, null, $exception);
                    throw $exception;
                }

                if (is_object($promise) && method_exists($promise, 'then')) {
                    return $promise->then(
                        function ($response) use ($collector, $request) {
                            self::record($collector, $request, $response, null);
                            return $response;
                        },
                        function ($reason) use ($collector, $request) {
                            $exception = $reason instanceof \Throwable ? $reason : null;
                            self::record($collector, $request, null, $exception);
                            if ($reason instanceof \Throwable) {
                                throw $reason;
                            }

                            throw new \RuntimeException('Guzzle request rejected.');
                        }
                    );
                }

                self::record($collector, $request, $promise, null);

                return $promise;
            };
        };
    }

    private static function record(Collector $collector, $request, $response, $exception)
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
        );

        if ($exception) {
            $event['error'] = get_class($exception) . ': ' . $exception->getMessage();
        }

        $collector->addExternalHttp($event);
    }
}
