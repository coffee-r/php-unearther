<?php

if ($_SERVER['REQUEST_URI'] !== '/authorize' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    echo json_encode(array('status' => 'not_found'));
    return;
}

header('Content-Type: application/json');
echo json_encode(array('status' => 'authorized'));
