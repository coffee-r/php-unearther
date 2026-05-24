<?php

namespace CoffeeR\Ci3Unearth\Sink;

interface SinkInterface
{
    public function write(array $trace);
}
