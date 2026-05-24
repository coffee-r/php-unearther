<?php

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/');
}
if (!defined('APPPATH')) {
    define('APPPATH', __DIR__ . '/application/');
}
if (!defined('FCPATH')) {
    define('FCPATH', __DIR__ . '/');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

if (!class_exists('CI_Loader')) {
    class CI_Loader
    {
        public function view($view, $vars = array(), $return = false)
        {
            return null;
        }
    }
}
