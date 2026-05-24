<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/Unearth_Loader_Trait.php';

class MY_Loader extends CI_Loader
{
    use Unearth_Loader_Trait;
}
