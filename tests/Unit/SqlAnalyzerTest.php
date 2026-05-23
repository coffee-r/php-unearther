<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Sql\SqlAnalyzer;
use CoffeeR\Unearther\Redaction\Redactor;
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
        $this->assertArrayNotHasKey('duration_ms', $event);
    }

    public function testAnalyzesInsertUpdateDeleteAndMerge()
    {
        $analyzer = new SqlAnalyzer();

        $this->assertSame(array('ORDERS'), $analyzer->tables('insert into orders (id) values (?)'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('update orders set status = ?'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('delete from orders where id = ?'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('merge into orders using dual on (id = ?)'));
    }

    public function testAnalyzesQuotedTableNames()
    {
        $analyzer = new SqlAnalyzer();

        $this->assertSame(array('USERS'), $analyzer->tables('INSERT INTO "users" ("email") VALUES (?)'));
        $this->assertSame(array('ORDERS'), $analyzer->tables('UPDATE `orders` SET status = ?'));
        $this->assertSame(array('ORDER_PRODUCTS'), $analyzer->tables('DELETE FROM [order_products] WHERE id = ?'));
    }

    public function testStatementHashIsLiteralInsensitive()
    {
        $analyzer = new SqlAnalyzer();

        $first = $analyzer->analyze("select * from users where id = 42 and name = 'coffee'");
        $second = $analyzer->analyze("select * from users where id = 99 and name = 'tea'");

        $this->assertSame($first['statement_hash'], $second['statement_hash']);
        $this->assertSame('select * from users where id = {parameter} and name = {parameter}', $first['statement_normalized']);
    }

    public function testStatementTextIsOptIn()
    {
        $sql = " select * from users where id = 42 and name = 'coffee' ";

        $defaultEvent = (new SqlAnalyzer())->analyze($sql);
        $this->assertNull($defaultEvent['statement_text']);
        $this->assertSame('select * from users where id = {parameter} and name = {parameter}', $defaultEvent['statement_normalized']);

        $capturedEvent = (new SqlAnalyzer(true))->analyze($sql);
        $this->assertSame($sql, $capturedEvent['statement_text']);
        $this->assertSame('select * from users where id = {parameter} and name = {parameter}', $capturedEvent['statement_normalized']);
    }

    public function testExtractsCommaSeparatedFromTables()
    {
        $analyzer = new SqlAnalyzer();

        $this->assertSame(
            array('T_ORDER', 'M_SHOHIN', 'T_CART'),
            $analyzer->tables('select * from T_ORDER o, M_SHOHIN s join T_CART c on c.order_id = o.id where o.id = ?')
        );
    }

    public function testAddsTokenBindRawAndAnalysisMetadata()
    {
        $analyzer = new SqlAnalyzer(true, new Redactor('secret', 12, array()), true);

        $event = $analyzer->analyze("select * from users where id = 42 and name = 'coffee'", array(42), array('source' => 'codeigniter3_query_history'));

        $this->assertMatchesRegularExpression('/\{p-[a-f0-9]{12}\}/', $event['statement_tokenized']);
        $this->assertMatchesRegularExpression('/^\{p-[a-f0-9]{12}\}$/', $event['bind_tokens'][0]);
        $this->assertStringContainsString($event['bind_tokens'][0], $event['statement_tokenized']);
        $this->assertSame(array(42), $event['bind_raw']);
        $this->assertSame('regex', $event['analysis']['analyzer']);
        $this->assertContains('query_history_capture_has_no_precise_caller_or_bind_values', $event['analysis']['warnings']);
    }
}
