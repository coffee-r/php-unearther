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

    public function testStatementHashUsesLiteralInsensitiveFingerprint()
    {
        $analyzer = new SqlAnalyzer();

        $first = $analyzer->analyze("select * from users where id = 42 and name = 'coffee'");
        $second = $analyzer->analyze("select * from users where id = 99 and name = 'tea'");

        $this->assertSame($first['statement_hash'], $second['statement_hash']);
        $this->assertSame('select * from users where id = ? and name = ?', $analyzer->fingerprint("select * from users where id = 42 and name = 'coffee'"));
    }

    public function testSqlTextCaptureIsOptIn()
    {
        $sql = " select * from users where id = 42 and name = 'coffee' ";

        $defaultEvent = (new SqlAnalyzer())->analyze($sql);
        $this->assertArrayNotHasKey('raw_sql', $defaultEvent);
        $this->assertArrayNotHasKey('normalized_sql', $defaultEvent);
        $this->assertArrayNotHasKey('fingerprint_sql', $defaultEvent);

        $capturedEvent = (new SqlAnalyzer(true))->analyze($sql);
        $this->assertSame($sql, $capturedEvent['raw_sql']);
        $this->assertSame("select * from users where id = 42 and name = 'coffee'", $capturedEvent['normalized_sql']);
        $this->assertSame('select * from users where id = ? and name = ?', $capturedEvent['fingerprint_sql']);
    }

    public function testExtractsCommaSeparatedFromTables()
    {
        $analyzer = new SqlAnalyzer();

        $this->assertSame(
            array('T_ORDER', 'M_SHOHIN', 'T_CART'),
            $analyzer->tables('select * from T_ORDER o, M_SHOHIN s join T_CART c on c.order_id = o.id where o.id = ?')
        );
    }
}
