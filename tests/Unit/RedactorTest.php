<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Redaction\Redactor;
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
}
