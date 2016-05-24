<?php

namespace Toby\MySQL;

class Decimal
{
    private $value;

    private function __construct($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param $value
     * @return static
     */
    public static function create($value)
    {
        return new static($value);
    }
}
