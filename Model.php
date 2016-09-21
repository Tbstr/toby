<?php

namespace Toby;

use \InvalidArgumentException;
use Toby\Exceptions\TobyException;

class Model
{
    /* static variables */
    private static $helpers = array();

    /* helper management */
    public static function registerHelper($functionName, $callable)
    {
        // cancellation
        if(!is_string($functionName))   throw new InvalidArgumentException('argument functionName is not of type string');
        if(!is_callable($callable))     throw new InvalidArgumentException('argument callable is not of type $callable');

        // check for existence
        if(isset(self::$helpers[$functionName])) throw new TobyException('Helper "'.$functionName.'" is already set');

        // register
        self::$helpers[$functionName] = $callable;
    }

    public function __call($name, $arguments)
    {
        // cancellation
        if(!isset(self::$helpers[$name])) throw new \Exception("call to undefined function $name");

        // call
        return call_user_func_array(self::$helpers[$name], $arguments);
    }

    /* to string */
    public function __toString()
    {
        return 'Model';
    }
}
