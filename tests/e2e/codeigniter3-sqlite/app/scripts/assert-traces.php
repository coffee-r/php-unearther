<?php

require_once __DIR__ . '/../vendor/autoload.php';

use CoffeeR\Unearth\Schema\ObservationValidator;

$root = dirname(__DIR__);
$logFiles = glob($root . '/runtime/logs/*.jsonl');
assertTrue(count($logFiles) === 1, 'expected exactly one JSONL log file');

$traces = array();
$validator = new ObservationValidator($root . '/vendor/coffee-r/php-unearth/docs/schema/observation-v1.schema.json');
foreach (file($logFiles[0], FILE_IGNORE_NEW_LINES) as $line) {
    if (trim($line) === '') {
        continue;
    }
    $trace = json_decode($line, true);
    assertTrue(is_array($trace), 'trace line must be valid JSON');
    $errors = $validator->validate($trace);
    assertSame(array(), $errors, 'trace must match observation schema: ' . implode(', ', $errors));
    $traces[] = $trace;
}

assertTrue(count($traces) >= 8, 'expected traces for all e2e requests');

$registerCreated = findTrace($traces, 'POST', '/api/users/register', 201);
$registerDuplicate = findTrace($traces, 'POST', '/api/users/register', 422);
$productsIndex = findTrace($traces, 'GET', '/api/products', 200);
$productView = findTrace($traces, 'GET', '/products/SKU-COFFEE', 200);
$dryRun = findTrace($traces, 'POST', '/api/orders/dry-run', 200);
$orderCreated = findTrace($traces, 'POST', '/api/orders', 201);
$orderRejected = findTrace($traces, 'POST', '/api/orders', 422);

assertSame('codeigniter3', $orderCreated['framework'], 'framework should be codeigniter3');
assertSame(1.0, (float) $orderCreated['sample_rate'], 'sample_rate should be fixed to 1.0');

assertSqlOperation($registerCreated, 'INSERT', 'USERS');
assertSqlOperation($registerDuplicate, 'SELECT', 'USERS');
assertSqlOperation($productsIndex, 'SELECT', 'PRODUCTS');
assertSqlOperation($productsIndex, 'SELECT', 'CATEGORIES');
assertSqlOperation($dryRun, 'SELECT', 'USERS');
assertSqlOperation($dryRun, 'SELECT', 'PRODUCTS');
assertSqlOperation($orderCreated, 'INSERT', 'ORDERS');
assertSqlOperation($orderCreated, 'INSERT', 'ORDER_PRODUCTS');
assertSqlOperation($orderRejected, 'SELECT', 'PRODUCTS');

assertSame('json', $productsIndex['http']['response_kind'], 'products index should be json');
assertSame('html', $productView['http']['response_kind'], 'product detail should be html');
assertTrue(isset($productView['http']['views'][0]), 'product detail should record a view');
assertSame('products/show', $productView['http']['views'][0]['name'], 'view name should be captured by MY_Loader');
assertSame('string', $productView['http']['views'][0]['vars_shape']['product']['code'], 'view vars shape should include product code');

assertTrue(isset($orderCreated['external_http'][0]), 'order create should record fake payment HTTP call');
assertSame('POST', $orderCreated['external_http'][0]['method'], 'payment call method should be POST');
assertSame('fake-payment', $orderCreated['external_http'][0]['host'], 'payment call host should be fake-payment');
assertSame('/authorize', $orderCreated['external_http'][0]['path'], 'payment call path should be /authorize');
assertSame(200, $orderCreated['external_http'][0]['status'], 'payment call should return 200');

$pdo = new PDO('sqlite:' . $root . '/runtime/db/e2e.sqlite');
$orders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$orderProducts = (int) $pdo->query('SELECT COUNT(*) FROM order_products')->fetchColumn();
assertSame(1, $orders, 'only the successful order should be persisted');
assertSame(2, $orderProducts, 'successful order should persist two order lines');
$order = $pdo->query('SELECT subtotal, shipping_fee, total FROM orders LIMIT 1')->fetch(PDO::FETCH_ASSOC);
assertSame(6100, (int) $order['subtotal'], 'order subtotal should be calculated');
assertSame(0, (int) $order['shipping_fee'], 'shipping should be free above 5000');
assertSame(6100, (int) $order['total'], 'order total should be calculated');

$exportLines = file($root . '/runtime/e2e-export.jsonl', FILE_IGNORE_NEW_LINES);
assertTrue(count($exportLines) >= 8, 'export should produce JSONL lines');
foreach ($exportLines as $line) {
    $exported = json_decode($line, true);
    assertTrue(is_array($exported), 'export line should be JSON');
    assertTrue(!isset($exported['http']['request_raw']), 'export should omit request_raw');
    foreach ($exported['sql'] as $sql) {
        assertTrue(!isset($sql['statement_text']), 'export should omit statement_text');
        assertTrue(!isset($sql['bind_raw']), 'export should omit bind_raw');
    }
}

$report = json_decode(file_get_contents($root . '/runtime/e2e-report.json'), true);
assertTrue(is_array($report), 'report should be JSON');
assertTrue(isset($report['endpoint_count']) && $report['endpoint_count'] >= 5, 'report should aggregate endpoints');

echo "CI3 SQLite e2e assertions passed\n";

function findTrace(array $traces, $method, $path, $status)
{
    foreach ($traces as $trace) {
        if (
            isset($trace['http']['method'], $trace['http']['path'], $trace['http']['status'])
            && $trace['http']['method'] === $method
            && $trace['http']['path'] === $path
            && (int) $trace['http']['status'] === (int) $status
        ) {
            return $trace;
        }
    }

    fail('trace not found: ' . $method . ' ' . $path . ' ' . $status);
}

function assertSqlOperation(array $trace, $operation, $table)
{
    foreach ($trace['sql'] as $sql) {
        if ($sql['operation'] === $operation && in_array($table, $sql['tables'], true)) {
            return;
        }
    }

    fail('SQL not found for ' . $operation . ' ' . $table . ' in ' . $trace['http']['method'] . ' ' . $trace['http']['path']);
}

function assertSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fail($message . ' expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        fail($message);
    }
}

function fail($message)
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}
