<?php

$root = dirname(__DIR__);
$probePath = $root . '/runtime/e2e-memory-probes.jsonl';
$outputPath = $root . '/runtime/e2e-memory.json';
$logFiles = glob($root . '/runtime/logs/*.jsonl');
$expectedCounts = array(100, 300, 1000);

assertTrue(is_file($probePath), 'memory probe file should exist');
assertTrue(count($logFiles) === 1, 'expected exactly one JSONL log file');

$tracesById = array();
foreach (file($logFiles[0], FILE_IGNORE_NEW_LINES) as $line) {
    if (trim($line) === '') {
        continue;
    }
    $trace = json_decode($line, true);
    if (is_array($trace) && isset($trace['trace_id'])) {
        $tracesById[$trace['trace_id']] = array(
            'trace' => $trace,
            'jsonl_line_bytes' => strlen($line),
        );
    }
}

$probes = array();
foreach (file($probePath, FILE_IGNORE_NEW_LINES) as $line) {
    if (trim($line) === '') {
        continue;
    }
    $probe = json_decode($line, true);
    assertTrue(is_array($probe), 'memory probe line should be valid JSON');
    assertTrue(isset($probe['trace_id'], $probe['query_count']), 'memory probe should include trace_id and query_count');
    $probes[(int) $probe['query_count']] = $probe;
}

$cases = array();
foreach ($expectedCounts as $expectedCount) {
    assertTrue(isset($probes[$expectedCount]), 'memory probe should exist for ' . $expectedCount . ' queries');
    $probe = $probes[$expectedCount];
    assertTrue(isset($tracesById[$probe['trace_id']]), 'trace should exist for memory probe ' . $expectedCount);

    $traceInfo = $tracesById[$probe['trace_id']];
    $trace = $traceInfo['trace'];
    assertSame('/api/memory/sampling', $trace['http']['path'], 'memory trace path should match');
    assertSame(200, (int) $trace['http']['status'], 'memory trace status should be 200');
    assertTrue(isset($trace['sql']) && count($trace['sql']) >= $expectedCount, 'memory trace should include at least ' . $expectedCount . ' SQL events');
    assertJoinSelectObserved($trace);

    $cases[] = array(
        'query_count' => $expectedCount,
        'trace_id' => $probe['trace_id'],
        'sql_count' => count($trace['sql']),
        'jsonl_line_bytes' => $traceInfo['jsonl_line_bytes'],
        'memory_limit' => $probe['memory_limit'],
        'memory_start' => $probe['memory_start'],
        'memory_start_real' => $probe['memory_start_real'],
        'memory_after_queries' => $probe['memory_after_queries'],
        'memory_after_queries_real' => $probe['memory_after_queries_real'],
        'memory_before_response' => $probe['memory_before_response'],
        'memory_before_response_real' => $probe['memory_before_response_real'],
        'memory_peak' => $probe['memory_peak'],
        'memory_peak_real' => $probe['memory_peak_real'],
        'memory_shutdown' => $probe['memory_shutdown'],
        'memory_shutdown_real' => $probe['memory_shutdown_real'],
        'memory_peak_after_shutdown' => $probe['memory_peak_after_shutdown'],
        'memory_peak_after_shutdown_real' => $probe['memory_peak_after_shutdown_real'],
    );
}

$summary = array(
    'php_version' => PHP_VERSION,
    'memory_source' => 'memory_get_usage and memory_get_peak_usage inside php:7.3-apache request',
    'cases' => $cases,
);

file_put_contents($outputPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "CI3 SQLite memory probes collected\n";

function assertJoinSelectObserved(array $trace)
{
    foreach ($trace['sql'] as $sql) {
        if (
            isset($sql['operation'], $sql['tables'])
            && $sql['operation'] === 'SELECT'
            && in_array('ORDERS', $sql['tables'], true)
            && in_array('ORDER_PRODUCTS', $sql['tables'], true)
        ) {
            return;
        }
    }

    fail('memory trace should include SELECT joining ORDERS and ORDER_PRODUCTS');
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
