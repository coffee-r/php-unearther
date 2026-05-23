<?php

use CoffeeR\Unearther\Adapter\CodeIgniter3\Hook;

class MY_Loader extends CI_Loader
{
    public function view($view, $vars = array(), $return = false)
    {
        if (class_exists(Hook::class)) {
            Hook::recordView($view, is_array($vars) ? $vars : array());
        }

        return parent::view($view, $vars, $return);
    }
}
