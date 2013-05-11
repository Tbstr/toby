<?php

// require config
require_once 'bootstrap.cfg.php';
require_once $tobyRoot.'/Toby.class.php';


// init & run
array_shift($argv);
    
Toby::init( $tobyRoot,
            $appRoot,
            $publicRoot,
            Toby::$SCOPE_LOCAL);

Toby::runAction(array_shift($argv), array_shift($argv), $argv);