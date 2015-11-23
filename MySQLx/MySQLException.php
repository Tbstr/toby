<?php

namespace Toby\MySQL;

class MySQLException extends \Exception
{
    function __construct($message = '', $code = 0, MySQLException $previous = null)
    {
        // parent constructor
        parent::__construct('Toby_MySQL: '.$message, $code, $previous);
    }
}