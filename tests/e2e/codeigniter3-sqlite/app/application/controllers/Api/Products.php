<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Products extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Product_model');
    }

    public function index()
    {
        $categoryId = (int) $this->input->get('category_id');
        $products = $this->Product_model->listByCategory($categoryId);

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array('products' => $products)));
    }
}
