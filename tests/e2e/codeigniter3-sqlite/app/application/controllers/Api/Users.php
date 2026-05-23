<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
    }

    public function register()
    {
        $input = $this->jsonInput();
        $errors = array();
        $name = trim(isset($input['name']) ? (string) $input['name'] : '');
        $email = trim(isset($input['email']) ? (string) $input['email'] : '');
        $password = isset($input['password']) ? (string) $input['password'] : '';

        if ($name === '') {
            $errors['name'] = 'required';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'invalid';
        } elseif ($this->User_model->findByEmail($email)) {
            $errors['email'] = 'duplicate';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'min_length_8';
        }

        if ($errors) {
            return $this->json(array('ok' => false, 'errors' => $errors), 422);
        }

        $user = $this->User_model->create($name, $email, $password);
        return $this->json(array('ok' => true, 'user' => $user), 201);
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
