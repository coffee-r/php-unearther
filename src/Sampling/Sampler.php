<?php

namespace CoffeeR\Ci3Unearth\Sampling;

class Sampler
{
    private $rate;

    public function __construct($rate)
    {
        $rate = (float) $rate;
        if ($rate < 0.0) {
            $rate = 0.0;
        }
        if ($rate > 1.0) {
            $rate = 1.0;
        }
        $this->rate = $rate;
    }

    public function shouldSample()
    {
        if ($this->rate >= 1.0) {
            return true;
        }
        if ($this->rate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() < $this->rate;
    }

    public function rate()
    {
        return $this->rate;
    }
}
