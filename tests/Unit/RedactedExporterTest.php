<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Export\RedactedExporter;
use PHPUnit\Framework\TestCase;

class RedactedExporterTest extends TestCase
{
    public function testRemovesRawFieldsRecursively()
    {
        $exported = (new RedactedExporter())->export(array(array(
            'redaction' => array('tokenized' => true),
            'http' => array('path_raw' => '/users/1', 'request_raw' => array('name' => 'coffee'), 'request_tokens' => array('name' => '{p-a}')),
            'sql' => array(array('statement_text' => 'select 1', 'bind_raw' => array(1), 'statement_tokenized' => 'select {p-a}')),
        )));

        $this->assertArrayNotHasKey('path_raw', $exported[0]['http']);
        $this->assertArrayNotHasKey('request_raw', $exported[0]['http']);
        $this->assertArrayNotHasKey('statement_text', $exported[0]['sql'][0]);
        $this->assertArrayNotHasKey('bind_raw', $exported[0]['sql'][0]);
        $this->assertSame(array('name' => '{p-a}'), $exported[0]['http']['request_tokens']);
    }

    public function testRemovesTokenFieldsWhenTraceWasNotTokenized()
    {
        $exported = (new RedactedExporter())->export(array(array(
            'redaction' => array('tokenized' => false),
            'http' => array('request_tokens' => array('id' => '{p-a}')),
            'sql' => array(array('statement_tokenized' => 'select {p-a}')),
        )));

        $this->assertArrayNotHasKey('request_tokens', $exported[0]['http']);
        $this->assertArrayNotHasKey('statement_tokenized', $exported[0]['sql'][0]);
    }
}
