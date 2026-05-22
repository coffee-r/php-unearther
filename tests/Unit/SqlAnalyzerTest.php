<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Sql\SqlAnalyzer;
use PHPUnit\Framework\TestCase;

class SqlAnalyzerTest extends TestCase
{
    public function testAnalyzesSelectWithJoin()
    {
        $analyzer = new SqlAnalyzer();
        $event = $analyzer->analyze(' select * from users u join orders o on o.user_id = u.id where u.id = ? ', array(1));

        $this->assertSame('SELECT', $event['operation']);
        $this->assertSame(array('USERS', 'ORDERS'), $event['tables']);
        $this->assertSame(array(0 => 'number'), $event['bind_shape']);
        $this->assertStringStartsWith('sha256:', $event['statement_hash']);
    }

    public function testAnalyzesInsertUpdateDeleteAndMerge()
    {
        $analyzer = new SqlAnalyzer();

        $this->assertSame(array('ORDERS'), $analyzer->tables('insert into orders (id) values (?)'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('update orders set status = ?'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('delete from orders where id = ?'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('merge into orders using dual on (id = ?)'));
    }
}
