<?php

namespace Toby;

use Toby\Exceptions\TobyException;

class Model
{
    /* static variables */
    private static $helpers = [];

    /* helper management */

    /**
     * Registers helper function to be called via this class. Executed with __call magic method.
     * 
     * @param string   $functionName
     * @param callable $callable
     *
     * @throws TobyException
     */
    public static function registerHelper($functionName, callable $callable)
    {
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
