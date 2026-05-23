<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Product_model extends CI_Model
{
    public function listByCategory($categoryId)
    {
        $this->db->select('products.id, products.code, products.name, products.price, categories.name AS category_name');
        $this->db->from('products');
        $this->db->join('categories', 'categories.id = products.category_id');
        $this->db->where('products.category_id', (int) $categoryId);
        $this->db->where('products.is_active', 1);
        $this->db->order_by('products.code', 'ASC');

        return $this->db->get()->result_array();
    }

    public function findByCode($code)
    {
        $this->db->select('products.id, products.category_id, products.code, products.name, products.price, categories.name AS category_name');
        $this->db->from('products');
        $this->db->join('categories', 'categories.id = products.category_id');
        $this->db->where('products.code', $code);
        $this->db->where('products.is_active', 1);

        return $this->db->get()->row_array();
    }
}
