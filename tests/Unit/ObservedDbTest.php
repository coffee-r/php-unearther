<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Adapter\CodeIgniter3\ObservedDb;
use CoffeeR\Unearther\Collector;
use CoffeeR\Unearther\Sampling\Sampler;
use CoffeeR\Unearther\Sink\SinkInterface;
use PHPUnit\Framework\TestCase;

class ObservedDbTest extends TestCase
{
    public function testRecordsSqlForSampledTrace()
    {
        $sink = new ObservedDbMemorySink();
        $collector = new Collector(new Sampler(1.0), $sink);
        $collector->start('legacy-api', 'codeigniter3');

        $db = new ObservedDb(new ObservedDbInnerStub('ok'), $collector);
        $this->assertSame('ok', $db->query('select * from users where id = ?', array(123)));
        $collector->finish();

        $this->assertCount(1, $sink->traces);
        $this->assertSame('SELECT', $sink->traces[0]['sql'][0]['operation']);
        $this->assertSame(array('USERS'), $sink->traces[0]['sql'][0]['tables']);
        $this->assertSame(array(0 => 'number'), $sink->traces[0]['sql'][0]['bind_shape']);
    }

    public function testDoesNotRecordSqlForUnsampledTrace()
    {
        $sink = new ObservedDbMemorySink();
        $collector = new Collector(new Sampler(0.0), $sink);
        $collector->start('legacy-api', 'codeigniter3');

        $inner = new ObservedDbInnerStub('ok');
        $db = new ObservedDb($inner, $collector);
        $this->assertSame('ok', $db->query('select * from users where id = ?', array(123)));
        $collector->finish();

        $this->assertSame(1, $inner->queryCount);
        $this->assertCount(0, $sink->traces);
    }
}

class ObservedDbInnerStub
{
    public $queryCount = 0;
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function query($sql, $binds = false, $returnObject = null)
    {
        $this->queryCount++;

        return $this->result;
    }
}

class ObservedDbMemorySink implements SinkInterface
{
    public $traces = array();

    public function write(array $trace)
    {
        $this->traces[] = $trace;
    }
}
