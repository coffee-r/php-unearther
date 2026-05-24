<?php

defined('BASEPATH') OR exit('No direct script access allowed');

$config['unearth'] = array(
    'enabled' => getenv('UNEARTH_ENABLED') !== '0',
    'service' => getenv('UNEARTH_SERVICE') ?: 'legacy-api',
    'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production',
    'failure_mode' => getenv('UNEARTH_FAILURE_MODE') ?: 'log',
    'sample_rate' => getenv('UNEARTH_SAMPLE_RATE') !== false ? (float) getenv('UNEARTH_SAMPLE_RATE') : 0.1,
    'sink' => array(
        'path' => APPPATH . 'logs/unearth-{date}.jsonl',
    ),
    'codeigniter3' => array(
        'sql_capture' => 'sampled_query_history',
    ),
    'redaction' => array(
        'secret' => getenv('UNEARTH_REDACTION_SECRET') ?: null,
    ),
    'http' => array(
        'capture_json_response_shape' => false,
        'endpoint_patterns' => array(
            // array('method' => 'GET', 'path' => '/users/{id}', 'name' => 'users.show'),
        ),
    ),
);
