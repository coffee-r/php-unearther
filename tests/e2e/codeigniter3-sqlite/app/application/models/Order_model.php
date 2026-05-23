<?php

defined('BASEPATH') OR exit('No direct script access allowed');

use CoffeeR\Unearth\Adapter\CodeIgniter3\Hook;
use CoffeeR\Unearth\Guzzle\UnearthMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class Order_model extends CI_Model
{
    public function quote(array $input)
    {
        $userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
        $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : array();
        $user = $this->db->get_where('users', array('id' => $userId), 1)->row_array();
        if (!$user) {
            return array('ok' => false, 'error_code' => 'user_not_found');
        }
        if (count($items) === 0) {
            return array('ok' => false, 'error_code' => 'items_required');
        }

        $lines = array();
        $subtotal = 0;
        foreach ($items as $item) {
            $code = isset($item['product_code']) ? (string) $item['product_code'] : '';
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            if ($quantity < 1) {
                return array('ok' => false, 'error_code' => 'invalid_quantity');
            }

            $product = $this->db->get_where('products', array('code' => $code, 'is_active' => 1), 1)->row_array();
            if (!$product) {
                return array('ok' => false, 'error_code' => 'product_not_found');
            }

            $lineTotal = (int) $product['price'] * $quantity;
            $subtotal += $lineTotal;
            $lines[] = array(
                'product_id' => (int) $product['id'],
                'product_code' => $product['code'],
                'quantity' => $quantity,
                'unit_price' => (int) $product['price'],
                'line_total' => $lineTotal,
            );
        }

        $shippingFee = $subtotal >= 5000 ? 0 : 300;

        return array(
            'ok' => true,
            'user_id' => $userId,
            'items' => $lines,
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'total' => $subtotal + $shippingFee,
        );
    }

    public function create(array $input)
    {
        $quote = $this->quote($input);
        if (!$quote['ok']) {
            return $quote;
        }

        $this->db->trans_begin();
        $now = date('c');
        $this->db->insert('orders', array(
            'user_id' => $quote['user_id'],
            'subtotal' => $quote['subtotal'],
            'shipping_fee' => $quote['shipping_fee'],
            'total' => $quote['total'],
            'created_at' => $now,
        ));
        $orderId = (int) $this->db->insert_id();

        foreach ($quote['items'] as $line) {
            $this->db->insert('order_products', array(
                'order_id' => $orderId,
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
            ));
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('ok' => false, 'error_code' => 'order_failed');
        }

        $payment = $this->authorizePayment($orderId, $quote['total']);
        $this->db->trans_commit();

        return array(
            'ok' => true,
            'order_id' => $orderId,
            'subtotal' => $quote['subtotal'],
            'shipping_fee' => $quote['shipping_fee'],
            'total' => $quote['total'],
            'payment_status' => $payment['status'],
        );
    }

    private function authorizePayment($orderId, $total)
    {
        $stack = HandlerStack::create();
        if (Hook::collector()) {
            $stack->push(UnearthMiddleware::create(Hook::collector()));
        }

        $client = new Client(array(
            'base_uri' => getenv('PAYMENT_BASE_URL') ?: 'http://fake-payment:8080',
            'handler' => $stack,
            'http_errors' => false,
            'timeout' => 5,
        ));
        $response = $client->post('/authorize', array(
            'json' => array('order_id' => $orderId, 'amount' => $total),
        ));

        $decoded = json_decode((string) $response->getBody(), true);
        return is_array($decoded) ? $decoded : array('status' => 'unknown');
    }
}
