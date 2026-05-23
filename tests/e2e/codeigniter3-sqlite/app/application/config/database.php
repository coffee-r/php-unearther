<?php

defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

$db['default'] = array(
    'dsn' => 'sqlite:' . FCPATH . 'runtime/db/e2e.sqlite',
    'hostname' => '',
    'username' => '',
    'password' => '',
    'database' => '',
    'dbdriver' => 'pdo',
    'subdriver' => 'sqlite',
    'dbprefix' => '',
    'pconnect' => false,
    'db_debug' => true,
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt' => false,
    'compress' => false,
    'stricton' => false,
    'failover' => array(),
    'save_queries' => false,
);
