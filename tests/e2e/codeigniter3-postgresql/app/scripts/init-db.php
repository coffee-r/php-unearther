<?php

$root = dirname(__DIR__);
$logDir = $root . '/runtime/logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
foreach (glob($logDir . '/*.jsonl') as $path) {
    unlink($path);
}
foreach (glob($root . '/runtime/e2e-*') as $path) {
    unlink($path);
}

$pdo = new PDO('pgsql:host=postgresql;port=5432;dbname=e2e', 'unearth', 'unearth');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");
$pdo->exec("
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL
)");
$pdo->exec("
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    category_id INTEGER NOT NULL REFERENCES categories(id),
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    price INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1
)");
$pdo->exec("
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    subtotal INTEGER NOT NULL,
    shipping_fee INTEGER NOT NULL,
    total INTEGER NOT NULL,
    created_at TEXT NOT NULL
)");
$pdo->exec("
CREATE TABLE order_products (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id),
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    unit_price INTEGER NOT NULL,
    line_total INTEGER NOT NULL
)");

$now = date('c');
$pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
    ->execute(array('Seed User', 'seed@example.test', password_hash('password123', PASSWORD_BCRYPT), $now, $now));
$pdo->exec("INSERT INTO categories (name) VALUES ('Coffee'), ('Goods')");
$pdo->exec("INSERT INTO products (category_id, code, name, price, is_active) VALUES
    (1, 'SKU-COFFEE', 'House Blend', 2800, 1),
    (1, 'SKU-ESPRESSO', 'Espresso Roast', 3300, 1),
    (2, 'SKU-MUG', 'Logo Mug', 1600, 1)
");

echo "initialized postgresql fixture\n";
