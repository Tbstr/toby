<?php

// require & init
require_once '../bootstrap.cfg.php';
require_once $tobyRoot.'/Toby.class.php';

Toby::init( $tobyRoot,
            $appRoot,
            $publicRoot,
            Toby::$SCOPE_WEB,
            empty($_GET['r']) ? 'index' : $_GET['r'] );