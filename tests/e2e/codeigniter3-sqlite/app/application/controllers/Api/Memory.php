<?php

defined('BASEPATH') OR exit('No direct script access allowed');

use CoffeeR\Ci3Unearth\Adapter\CodeIgniter3\Hook;

class Memory extends MY_Controller
{
    private $allowedQueryCounts = array(100, 300, 1000);

    public function sampling()
    {
        $queryCount = (int) $this->input->get('queries');
        if (!in_array($queryCount, $this->allowedQueryCounts, true)) {
            return $this->json(array(
                'ok' => false,
                'error_code' => 'invalid_query_count',
                'allowed_query_counts' => $this->allowedQueryCounts,
            ), 422);
        }

        $traceId = $this->currentTraceId();
        $memoryStart = memory_get_usage(false);
        $memoryStartReal = memory_get_usage(true);

        for ($i = 0; $i < $queryCount; $i++) {
            $orderId = ($i % 3) + 1;
            $this->db->query(
                'SELECT orders.id, orders.total, order_products.product_id, order_products.quantity ' .
                'FROM orders ' .
                'JOIN order_products ON order_products.order_id = orders.id ' .
                'WHERE orders.id = ?',
                array($orderId)
            )->result_array();
        }

        $memoryAfterQueries = memory_get_usage(false);
        $memoryAfterQueriesReal = memory_get_usage(true);

        $body = array(
            'ok' => true,
            'trace_id' => $traceId,
            'query_count' => $queryCount,
            'memory_limit' => ini_get('memory_limit'),
            'memory_start' => $memoryStart,
            'memory_start_real' => $memoryStartReal,
            'memory_after_queries' => $memoryAfterQueries,
            'memory_after_queries_real' => $memoryAfterQueriesReal,
            'memory_before_response' => memory_get_usage(false),
            'memory_before_response_real' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(false),
            'memory_peak_real' => memory_get_peak_usage(true),
        );

        $this->recordProbeAfterShutdown($body);

        return $this->json($body, 200);
    }

    private function currentTraceId()
    {
        $collector = Hook::collector();
        $trace = $collector ? $collector->current() : null;

        return $trace ? $trace->getTraceId() : null;
    }

    private function recordProbeAfterShutdown(array $probe)
    {
        $path = FCPATH . 'runtime/e2e-memory-probes.jsonl';
        register_shutdown_function(function () use ($path, $probe) {
            $probe['memory_shutdown'] = memory_get_usage(false);
            $probe['memory_shutdown_real'] = memory_get_usage(true);
            $probe['memory_peak_after_shutdown'] = memory_get_peak_usage(false);
            $probe['memory_peak_after_shutdown_real'] = memory_get_peak_usage(true);

            $line = json_encode($probe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($line !== false) {
                file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
            }
        });
    }

    private function json(array $body, $status)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($body));
    }
}
