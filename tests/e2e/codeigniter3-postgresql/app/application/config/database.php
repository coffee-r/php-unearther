<?php

defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = true;

$db['default'] = array(
    'dsn'      => '',
    'hostname' => 'postgresql',
    'port'     => '5432',
    'username' => 'unearth',
    'password' => 'unearth',
    'database' => 'e2e',
    'dbdriver' => 'postgre',
    'dbprefix' => '',
    'pconnect' => false,
    'db_debug' => true,
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt'  => false,
    'compress' => false,
    'stricton' => false,
    'failover' => array(),
    'save_queries' => false,
);
