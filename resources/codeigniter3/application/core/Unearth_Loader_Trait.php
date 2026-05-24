<?php

defined('BASEPATH') OR exit('No direct script access allowed');

trait Unearth_Loader_Trait
{
    public function view($view, $vars = array(), $return = false)
    {
        if (class_exists('CoffeeR\\Unearth\\Adapter\\CodeIgniter3\\Hook')) {
            CoffeeR\Unearth\Adapter\CodeIgniter3\Hook::recordView($view, is_array($vars) ? $vars : array());
        }

        return parent::view($view, $vars, $return);
    }
}
