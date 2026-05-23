<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Products extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Product_model');
    }

    public function show($code)
    {
        $product = $this->Product_model->findByCode($code);
        if (!$product) {
            show_404();
            return;
        }

        $this->output->set_content_type('text/html');
        $this->load->view('products/show', array('product' => $product));
    }
}
