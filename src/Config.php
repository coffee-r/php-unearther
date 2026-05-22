<?php

namespace CoffeeR\Unearther;

class Config
{
    private $values;

    public function __construct(array $values = array())
    {
        $this->values = array_merge(self::defaults(), $values);

        if (isset($this->values['sink']) && is_array($this->values['sink'])) {
            $this->values['sink'] = array_merge(self::defaults()['sink'], $this->values['sink']);
        }
    }

    public static function defaults()
    {
        return array(
            'enabled' => true,
            'service' => 'legacy-api',
            'framework' => 'php',
            'sample_rate' => 1.0,
            'sink' => array(
                'type' => 'jsonl',
                'path' => sys_get_temp_dir() . '/php-unearther/observations-{date}.jsonl',
                'date_format' => 'Y-m-d',
            ),
        );
    }

    public static function fromArray(array $values)
    {
        return new self($values);
    }

    public function isEnabled()
    {
        return (bool) $this->values['enabled'];
    }

    public function service()
    {
        return $this->values['service'];
    }

    public function framework()
    {
        return $this->values['framework'];
    }

    public function sampleRate()
    {
        return (float) $this->values['sample_rate'];
    }

    public function sinkType()
    {
        return $this->values['sink']['type'];
    }

    public function sinkPath()
    {
        return $this->values['sink']['path'];
    }

    public function sinkDateFormat()
    {
        return $this->values['sink']['date_format'];
    }

    public function toArray()
    {
        return $this->values;
    }
}
