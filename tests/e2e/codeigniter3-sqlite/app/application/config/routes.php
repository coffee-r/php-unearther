<?php

defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'products/show/SKU-COFFEE';
$route['404_override'] = '';
$route['translate_uri_dashes'] = false;

$route['api/users/register']['post'] = 'api/users/register';
$route['api/memory/sampling']['get'] = 'api/memory/sampling';
$route['api/products']['get'] = 'api/products/index';
$route['api/orders/dry-run']['post'] = 'api/orders/dry_run';
$route['api/orders']['post'] = 'api/orders/create';
$route['products/(:any)']['get'] = 'products/show/$1';
