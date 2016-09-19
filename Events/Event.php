<?php

namespace Toby\Events;

class Event
{
    public $type    = null;
    public $data    = null;

    /**
     * Event constructor.
     *
     * @param string $type
     * @param null $data
     */
    function __construct($type, $data = null)
    {
        // set vars
        $this->type = $type;
        $this->data = null;
    }
}