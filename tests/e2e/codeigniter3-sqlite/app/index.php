<?php

define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'testing');

$system_path = __DIR__ . '/vendor/codeigniter/framework/system';
$application_folder = __DIR__ . '/application';
$view_folder = '';

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', str_replace('\\', '/', $system_path) . '/');
define('FCPATH', __DIR__ . '/');
define('SYSDIR', basename(BASEPATH));
define('APPPATH', str_replace('\\', '/', $application_folder) . '/');
define('VIEWPATH', APPPATH . 'views/');

require_once BASEPATH . 'core/CodeIgniter.php';
