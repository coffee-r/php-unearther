<?php

defined('BASEPATH') OR exit('No direct script access allowed');

use CoffeeR\Unearth\Adapter\CodeIgniter3\Hook;

class UnearthHook
{
    private $hook;

    public function __construct()
    {
        $autoload = FCPATH . 'vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        $this->hook = class_exists(Hook::class) ? new Hook() : null;
    }

    public function start($config = array())
    {
        if ($this->hook) {
            $this->hook->start(is_array($config) ? $config : array());
        }
    }

    public function finish()
    {
        if ($this->hook) {
            $this->hook->finish();
        }
    }
}
