<?php

use CoffeeR\Ci3Unearth\Adapter\CodeIgniter3\Hook;

class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (isset($this->db) && class_exists(Hook::class)) {
            Hook::observeDb($this->db, 'default');
        }
    }
}
