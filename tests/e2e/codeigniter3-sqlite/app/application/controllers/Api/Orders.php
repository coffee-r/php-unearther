<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Orders extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Order_model');
    }

    public function dry_run()
    {
        $result = $this->Order_model->quote($this->jsonInput());
        if (!$result['ok']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 200);
    }

    public function create()
    {
        $result = $this->Order_model->create($this->jsonInput());
        if (!$result['ok']) {
            return $this->json($result, 422);
        }

        return $this->json($result, 201);
    }

    private function jsonInput()
    {
        $decoded = json_decode($this->input->raw_input_stream, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function json(array $body, $status)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($body));
    }
}
