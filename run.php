<?php

// change directory & require
chdir(__DIR__);
require_once 'app/core/Core.class.php';

// define scope
define('SCOPE', 'local');

// init & run
array_shift($argv);
    
Core::init();
Core::runAction(array_shift($argv), array_shift($argv), $argv);