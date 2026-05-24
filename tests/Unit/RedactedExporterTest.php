<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit;

use CoffeeR\Ci3Unearth\Export\RedactedExporter;
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

    public function testPreservesEmptyShapeKeysAsJsonObject()
    {
        $exported = (new RedactedExporter())->export(array(array(
            'redaction' => array('tokenized' => false),
            'http' => array(
                'request_shape' => array(),
                'response_shape' => array(),
                'query_shape' => array(),
            ),
            'sql' => array(array(
                'bind_shape' => array(),
            )),
        )));

        $this->assertInstanceOf(\stdClass::class, $exported[0]['http']['request_shape']);
        $this->assertInstanceOf(\stdClass::class, $exported[0]['http']['response_shape']);
        $this->assertInstanceOf(\stdClass::class, $exported[0]['http']['query_shape']);
        $this->assertInstanceOf(\stdClass::class, $exported[0]['sql'][0]['bind_shape']);

        $json = json_encode($exported[0]);
        $this->assertStringContainsString('"request_shape":{}', $json);
        $this->assertStringContainsString('"bind_shape":{}', $json);
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
