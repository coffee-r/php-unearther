<?php

namespace CoffeeR\Unearther\Sink;

class NullSink implements SinkInterface
{
    public function write(array $trace)
    {
    }
}
