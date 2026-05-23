<?php

namespace CoffeeR\Unearth\Tests\Unit;

use CoffeeR\Unearth\Redaction\Redactor;
use PHPUnit\Framework\TestCase;

class RedactorTest extends TestCase
{
    public function testTokenizesStableValuesAndRedactsDeniedKeys()
    {
        $redactor = new Redactor('secret', 12, array('password', 'token'));

        $this->assertSame($redactor->token('abc'), $redactor->token('abc'));
        $this->assertMatchesRegularExpression('/^\{p-[a-f0-9]{12}\}$/', $redactor->token('abc'));
        $this->assertSame('{redacted}', $redactor->tokens('hidden', 'api_token'));
        $this->assertSame(array('id' => $redactor->token(123)), $redactor->tokens(array('id' => 123)));
    }

    public function testNumericStringsAndNumbersShareTokens()
    {
        $redactor = new Redactor('secret', 12, array());

        $this->assertSame($redactor->token(42), $redactor->token('42'));
        $this->assertSame($redactor->token(42.0), $redactor->token('42.0'));
        $this->assertNotSame($redactor->token(42), $redactor->token('042'));
    }

    public function testSkipsTokensWithoutSecret()
    {
        $redactor = new Redactor(null, 12, array());

        $this->assertNull($redactor->token('abc'));
        $this->assertNull($redactor->tokens(array('id' => 123)));
    }

    public function testTokenizedSqlReplacesNumericAndQuotedLiterals()
    {
        $redactor = new Redactor('secret', 12, array());

        $tokenized = $redactor->tokenizedSql("SELECT * FROM users WHERE id = 42 AND name = 'coffee'");

        $this->assertMatchesRegularExpression(
            "/^SELECT \\* FROM users WHERE id = \\{p-[a-f0-9]{12}\\} AND name = \\{p-[a-f0-9]{12}\\}$/",
            $tokenized
        );
        $this->assertStringContainsString($redactor->token('42'), $tokenized);
        $this->assertStringContainsString($redactor->token('coffee'), $tokenized);
    }

    public function testTokenizedSqlHandlesDoubledSingleQuoteEscape()
    {
        $redactor = new Redactor('secret', 12, array());

        $tokenized = $redactor->tokenizedSql("UPDATE notes SET body = 'hello''world' WHERE id = 7");

        $this->assertStringContainsString($redactor->token("hello'world"), $tokenized);
        $this->assertStringContainsString($redactor->token('7'), $tokenized);
        $this->assertStringNotContainsString("'", $tokenized);
    }

    public function testTokenizedSqlReturnsNullWithoutSecret()
    {
        $redactor = new Redactor(null, 12, array());

        $this->assertNull($redactor->tokenizedSql("SELECT * FROM users WHERE id = 1"));
    }
}
