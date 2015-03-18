<?php

class Toby_Autoloader
{
    /* variables */
    private static $entries = array();

    /* class management */
    public static function addEntry($className, $pathToFile)
    {
        // check for existing entry
        $entry = self::getEntryByClassName($className);
        if($entry !== false) return false;

        // create entry
        self::$entries[] = new Toby_Autoloader_Entry($className, $pathToFile);

        // return
        return true;
    }

    private static function getEntryByClassName($className)
    {
        foreach(self::$entries as $entry)
        {
            if($entry->className === $className) return $entry;
        }

        return false;
    }

    /* load */
    public static function load($className)
    {
        // check entries
        $entry = self::getEntryByClassName($className);
        if($entry !== false)
        {
            require_once $entry->pathToFile;
            return;
        }

        // load within framework
        self::loadToby($className);
    }

    private static function loadToby($className)
    {
        // prepare
        $elements = explode('_', $className);

        if(count($elements) === 1) return;
        $basename = array_pop($elements);

        // resolve toby related
        if(strtolower($elements[0]) === 'toby')
        {
            $elements[0]    = TOBY_ROOT;
            $path = strtolower(implode('/', $elements))."/$className.class.php";

            if(is_file($path)) require_once($path);
            else
            {
                $path = strtolower(implode('/', $elements)).'/'.strtolower($basename)."/$className.class.php";
                if(is_file($path)) require_once($path);
            }
        }

        // resolve app related
        else
        {
            array_unshift($elements, APP_ROOT);
            $path = strtolower(implode('/', $elements))."/$className.class.php";

            if(is_file($path)) require_once($path);
            else
            {
                $path = strtolower(implode('/', $elements)).'/'.strtolower($basename)."/$className.class.php";
                if(is_file($path)) require_once($path);
            }
        }
    }
}

class Toby_Autoloader_Entry
{
    /* variables */
    public $className;
    public $pathToFile;

    function __construct($className, $pathToFile)
    {
        // vars
        $this->className    = $className;
        $this->pathToFile   = $pathToFile;
    }
}