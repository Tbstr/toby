<?php

namespace Toby\Utils;

class SysUtils
{
    public static function printr()
    {
        $args = func_get_args();
        foreach($args as $arg)
        {
            if($arg === null) $arg = 'null';
            elseif($arg === true) $arg = 'true';
            elseif($arg === false) $arg = 'false';

            echo '<pre>'.print_r($arg, true).'</pre>';
        }
    }

    public static function listExtensions()
    {
        self::printr(get_loaded_extensions());
    }

    public static function extensionLoaded($name)
    {
        return in_array($name, get_loaded_extensions());
    }
}
