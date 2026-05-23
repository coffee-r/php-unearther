<?php

$root = dirname(__DIR__);
$runtime = $root . '/runtime';
$dbDir = $runtime . '/db';
$logDir = $runtime . '/logs';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

foreach (glob($logDir . '/*.jsonl') as $path) {
    unlink($path);
}
foreach (glob($runtime . '/e2e-*') as $path) {
    unlink($path);
}

$dbPath = $dbDir . '/e2e.sqlite';
if (is_file($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);
CREATE TABLE products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    price INTEGER NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    subtotal INTEGER NOT NULL,
    shipping_fee INTEGER NOT NULL,
    total INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE order_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price INTEGER NOT NULL,
    line_total INTEGER NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
");

$now = date('c');
$pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
    ->execute(array('Seed User', 'seed@example.test', password_hash('password123', PASSWORD_BCRYPT), $now, $now));
$pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'Coffee'), (2, 'Goods')");
$pdo->exec("INSERT INTO products (category_id, code, name, price, is_active) VALUES
    (1, 'SKU-COFFEE', 'House Blend', 2800, 1),
    (1, 'SKU-ESPRESSO', 'Espresso Roast', 3300, 1),
    (2, 'SKU-MUG', 'Logo Mug', 1600, 1)
");

echo "initialized sqlite fixture\n";
