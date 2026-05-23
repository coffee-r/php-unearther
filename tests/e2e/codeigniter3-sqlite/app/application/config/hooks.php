<?php

defined('BASEPATH') OR exit('No direct script access allowed');

$unearthConfig = array(
    'service' => 'ci3-sqlite-e2e',
    'environment' => 'e2e',
    'sample_rate' => 1.0,
    'sink' => array(
        'path' => FCPATH . 'runtime/logs/unearth-{date}.jsonl',
    ),
    'codeigniter3' => array(
        'sql_capture' => 'sampled_query_history',
    ),
    'http' => array(
        'capture_json_response_shape' => true,
        'endpoint_patterns' => array(
            array('method' => 'POST', 'path' => '/api/users/register', 'name' => 'users.register'),
            array('method' => 'GET', 'path' => '/api/products', 'name' => 'products.index'),
            array('method' => 'GET', 'path' => '/products/{code}', 'name' => 'products.show'),
            array('method' => 'POST', 'path' => '/api/orders/dry-run', 'name' => 'orders.dry_run'),
            array('method' => 'POST', 'path' => '/api/orders', 'name' => 'orders.create'),
        ),
    ),
);

$hook['post_controller_constructor'][] = array(
    'class' => 'UnearthHook',
    'function' => 'start',
    'filename' => 'UnearthHook.php',
    'filepath' => 'hooks',
    'params' => $unearthConfig,
);

$hook['post_system'][] = array(
    'class' => 'UnearthHook',
    'function' => 'finish',
    'filename' => 'UnearthHook.php',
    'filepath' => 'hooks',
    'params' => array(),
);
