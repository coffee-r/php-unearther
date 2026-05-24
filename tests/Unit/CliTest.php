<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    public function testReportCommandUsesAutoloadAndWritesJson()
    {
        $command = $this->command('report ' . escapeshellarg(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl') . ' --format json 2>&1');

        exec($command, $output, $exitCode);
        $json = implode("\n", $output);
        $decoded = json_decode($json, true);

        $this->assertSame(0, $exitCode, $json);
        $this->assertIsArray($decoded);
        $this->assertSame('observed_behavior', $decoded['report_kind']);
        $this->assertSame(1, $decoded['observed_entrypoint_count']);
        $this->assertArrayNotHasKey('endpoint_count', $decoded);
        $this->assertArrayNotHasKey('endpoints', $decoded);
    }

    public function testReportCommandWarnsAboutInvalidJsonl()
    {
        $path = sys_get_temp_dir() . '/php-ci3-unearth-cli-test-' . uniqid('', true) . '.jsonl';
        file_put_contents($path, "not-json\n");

        $command = $this->command('report ' . escapeshellarg($path) . ' --format md 2>&1');
        exec($command, $output, $exitCode);
        @unlink($path);

        $text = implode("\n", $output);
        $this->assertSame(0, $exitCode, $text);
        $this->assertStringContainsString('Warning: Invalid JSONL', $text);
    }

    public function testReportCommandRejectsMissingInputAndUnknownFormat()
    {
        exec($this->command('report 2>&1'), $missingOutput, $missingExitCode);
        $this->assertSame(1, $missingExitCode);
        $this->assertStringContainsString('No JSONL files given.', implode("\n", $missingOutput));

        $fixture = escapeshellarg(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl');
        exec($this->command('report ' . $fixture . ' --format xml 2>&1'), $formatOutput, $formatExitCode);
        $this->assertSame(1, $formatExitCode);
        $this->assertStringContainsString('Unknown format: xml', implode("\n", $formatOutput));
    }

    public function testExportCommandWritesRedactedJsonl()
    {
        $command = $this->command('export ' . escapeshellarg(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl') . ' --profile ai --format jsonl 2>&1');

        exec($command, $output, $exitCode);
        $decoded = json_decode($output[0], true);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('request_raw', $decoded['http']);
        $this->assertArrayNotHasKey('statement_text', $decoded['sql'][0]);
    }

    public function testReportCommandSupportsRawValueMode()
    {
        $path = sys_get_temp_dir() . '/php-ci3-unearth-cli-raw-test-' . uniqid('', true) . '.jsonl';
        file_put_contents($path, json_encode(array(
            'schema_version' => 1,
            'trace_id' => 'raw',
            'service' => 'legacy-api',
            'framework' => 'codeigniter3',
            'environment' => 'test',
            'sampled' => true,
            'sample_rate' => 1.0,
            'started_at' => '2026-06-01T00:00:00+00:00',
            'redaction' => array('tokenized' => false, 'token_format' => null),
            'http' => array('method' => 'GET', 'path' => '/x', 'path_pattern' => '/x', 'status' => 200),
            'sql' => array(array(
                'seq' => 1,
                'operation' => 'SELECT',
                'tables' => array('USERS'),
                'statement_normalized' => 'select * from users where id = {parameter}',
                'statement_tokenized' => null,
                'statement_text' => 'select * from users where id = 1',
                'statement_hash' => 'sha256:test',
                'bind_shape' => array(),
                'bind_tokens' => null,
                'bind_raw' => null,
                'analysis' => array('analyzer' => 'regex', 'operation_confidence' => 'high', 'tables_confidence' => 'best_effort', 'warnings' => array()),
            )),
            'external_http' => array(),
            'errors' => array(),
        )) . "\n");

        exec($this->command('report ' . escapeshellarg($path) . ' --format md 2>&1'), $normalOutput, $normalExitCode);
        exec($this->command('report ' . escapeshellarg($path) . ' --format md --value-mode raw 2>&1'), $rawOutput, $rawExitCode);
        @unlink($path);

        $this->assertSame(0, $normalExitCode);
        $this->assertSame(0, $rawExitCode);
        $this->assertStringNotContainsString('concrete:', implode("\n", $normalOutput));
        $this->assertStringContainsString('concrete:', implode("\n", $rawOutput));
    }

    public function testReportCommandSupportsTableCatalog()
    {
        $catalogPath = sys_get_temp_dir() . '/php-ci3-unearth-table-catalog-' . uniqid('', true) . '.json';
        file_put_contents($catalogPath, json_encode(array(
            'M_SHOHIN' => '商品マスタ。',
            'T_CART' => 'カート明細。',
        )));

        $fixture = escapeshellarg(__DIR__ . '/../Fixtures/jsonl/cart_add.jsonl');
        exec($this->command('report ' . $fixture . ' --format json --table-catalog ' . escapeshellarg($catalogPath) . ' 2>&1'), $output, $exitCode);
        @unlink($catalogPath);

        $decoded = json_decode(implode("\n", $output), true);
        $catalog = $decoded['observed_entrypoints'][0]['table_catalog'];

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertSame('M_SHOHIN', $catalog[0]['table']);
        $this->assertSame('商品マスタ。', $catalog[0]['description']);
        $this->assertSame('T_CART', $catalog[1]['table']);
        $this->assertSame('カート明細。', $catalog[1]['description']);
    }

    private function command($arguments)
    {
        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../bin/unearth') . ' ' . $arguments;
    }
}
