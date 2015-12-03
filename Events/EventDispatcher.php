<?php

namespace Toby\Events;

use InvalidArgumentException;

class EventDispatcher
{
    private $event_listeners = array();

    /**
     * @param string   $type
     * @param callable $callable
     */
    public function addEventListener($type, callable $callable)
    {
        // cancellation
        if(!is_string($type) || empty($type)) throw new InvalidArgumentException('argument $type is not of type string or empty');

        // add
        $this->event_listeners[] = array($type, $callable);
    }

    /**
     * @param string $type
     * @param mixed  $data
     */
    public function triggerEvent($type, $data = null)
    {
        // cancellation
        if(!is_string($type) || empty($type)) throw new InvalidArgumentException('argument $type is not of type string or empty');

        // create event
        $e = new Event($type, $data);

        // call listeners
        foreach($this->event_listeners as $listener)
        {
            if($listener[0] === $type) call_user_func($listener[1], $e);
        }
    }
}