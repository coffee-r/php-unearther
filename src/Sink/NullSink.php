<?php

namespace CoffeeR\Unearth\Sink;

class NullSink implements SinkInterface
{
    public function write(array $trace)
    {
    }
}
