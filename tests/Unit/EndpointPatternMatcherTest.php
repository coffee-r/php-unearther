<?php

namespace CoffeeR\Unearther\Tests\Unit;

use CoffeeR\Unearther\Http\EndpointPatternMatcher;
use PHPUnit\Framework\TestCase;

class EndpointPatternMatcherTest extends TestCase
{
    public function testMatchesLaravelStyleSegmentPlaceholders()
    {
        $matcher = new EndpointPatternMatcher();

        $this->assertSame(array(
            'endpoint_path' => '/api/users/{id}',
            'endpoint_name' => 'users.show',
        ), $matcher->match('GET', '/api/users/123?debug=1', array(
            array('method' => 'GET', 'path' => '/api/users/{id}', 'name' => 'users.show'),
        )));
    }

    public function testDoesNotInferUnconfiguredPaths()
    {
        $matcher = new EndpointPatternMatcher();

        $this->assertNull($matcher->match('GET', '/api/users/123', array(
            array('method' => 'POST', 'path' => '/api/users/{id}'),
            array('method' => 'GET', 'path' => '/api/users/{id}/orders'),
        )));
    }

    public function testFirstMatchWins()
    {
        $matcher = new EndpointPatternMatcher();

        $this->assertSame(array(
            'endpoint_path' => '/api/users/{id}',
            'endpoint_name' => 'first',
        ), $matcher->match('GET', '/api/users/123', array(
            array('method' => 'GET', 'path' => '/api/users/{id}', 'name' => 'first'),
            array('method' => 'GET', 'path' => '/api/users/{user_id}', 'name' => 'second'),
        )));
    }
}
