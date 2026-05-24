<?php

namespace CoffeeR\Ci3Unearth\Tests\Unit\Schema;

use CoffeeR\Ci3Unearth\Report\JsonlReader;
use CoffeeR\Ci3Unearth\Schema\ObservationValidator;
use PHPUnit\Framework\TestCase;

class ObservationValidatorTest extends TestCase
{
    public function testFixturesConformToObservationSchemaContract()
    {
        $reader = new JsonlReader();
        $traces = $reader->read(array(
            __DIR__ . '/../../Fixtures/jsonl/cart_add.jsonl',
            __DIR__ . '/../../Fixtures/jsonl/order_create.jsonl',
        ));
        $validator = new ObservationValidator(__DIR__ . '/../../../docs/schema/observation-v1.schema.json');

        foreach ($traces as $trace) {
            $this->assertSame(array(), $validator->validate($trace), $trace['trace_id']);
        }
    }

    public function testValidatorUsesJsonSchemaFileAsContract()
    {
        $validator = new ObservationValidator(__DIR__ . '/../../../docs/schema/observation-v1.schema.json');
        $trace = array(
            'schema_version' => 1,
            'trace_id' => 'schema-test',
            'service' => 'legacy-api',
            'framework' => 'codeigniter3',
            'environment' => 'test',
            'sampled' => true,
            'sample_rate' => 0.1,
            'started_at' => '2026-06-01T00:00:00+00:00',
            'redaction' => array('tokenized' => false, 'token_format' => null),
            'http' => array('method' => 'GET', 'path' => '/ping', 'path_pattern' => '/ping'),
            'sql' => array(),
            'external_http' => array(),
            'errors' => array(),
        );

        unset($trace['framework']);

        $this->assertContains('missing required $.framework', $validator->validate($trace));
    }
}
