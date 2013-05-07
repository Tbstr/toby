<?php

// change directory & require
chdir(__DIR__);
require_once 'app/core/Core.class.php';

// define scope
define('SCOPE', 'web');

// init
Core::init(empty($_GET['r']) ? 'index' : $_GET['r']);