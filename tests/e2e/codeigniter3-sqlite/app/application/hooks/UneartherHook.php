<?php

use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;

class UneartherHook
{
    private $hook;

    public function __construct()
    {
        require_once FCPATH . 'vendor/autoload.php';
        $this->hook = new Hook();
    }

    public function start($config = array())
    {
        $this->mark('start');
        $this->hook->start($config);
    }

    public function finish($config = array())
    {
        $this->mark('finish');
        $this->hook->finish($config);
    }

    private function mark($event)
    {
        if (!getenv('UNEARTHER_E2E_HOOK_MARKERS')) {
            return;
        }

        @file_put_contents(FCPATH . 'runtime/hook-events.log', $event . "\n", FILE_APPEND);
    }
}
