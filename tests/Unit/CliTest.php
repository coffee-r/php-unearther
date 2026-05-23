<?php

namespace CoffeeR\Unearther\Tests\Unit;

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
        $this->assertSame(1, $decoded['endpoint_count']);
    }

    public function testReportCommandWarnsAboutInvalidJsonl()
    {
        $path = sys_get_temp_dir() . '/php-unearther-cli-test-' . uniqid('', true) . '.jsonl';
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

    private function command($arguments)
    {
        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../bin/unearth') . ' ' . $arguments;
    }
}
