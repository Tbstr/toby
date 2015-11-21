<?php

namespace Toby\MySQL;

class Exception extends \Exception
{
    function __construct($message = '', $code = 0, Exception $previous = null)
    {
        // parent constructor
        parent::__construct('Toby_MySQL: '.$message, $code, $previous);
    }
}