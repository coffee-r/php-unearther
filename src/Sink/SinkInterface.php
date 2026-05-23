<?php

namespace CoffeeR\Unearth\Sink;

interface SinkInterface
{
    public function write(array $trace);
}
