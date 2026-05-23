<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    public function findByEmail($email)
    {
        return $this->db->get_where('users', array('email' => $email), 1)->row_array();
    }

    public function findById($id)
    {
        return $this->db->get_where('users', array('id' => (int) $id), 1)->row_array();
    }

    public function create($name, $email, $password)
    {
        $now = date('c');
        $this->db->insert('users', array(
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'created_at' => $now,
            'updated_at' => $now,
        ));

        return $this->findById((int) $this->db->insert_id());
    }
}
