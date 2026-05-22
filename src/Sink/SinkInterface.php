<?php

namespace CoffeeR\Unearther\Sink;

interface SinkInterface
{
    public function write(array $trace);
}
