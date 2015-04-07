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

    public static function addEntries($entries)
    {
        // cancellation
        if(!is_array($entries)) throw new InvalidArgumentException('argument entries is not of type array');

        // add
        foreach($entries as $className => $path) self::addEntry($className, $path);

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
        // vars
        $namespaceElements  = explode('\\', $className);

        $className          = array_pop($namespaceElements);
        $classNameElements  = explode('_', $className);

        if(count($classNameElements) === 1) return;
        $classNameBase      = array_pop($classNameElements);

        $namespacePath      = empty($namespaceElements) ? '' : implode('/', $namespaceElements).'/';

        // resolve toby related
        if(strtolower($classNameElements[0]) === 'toby')
        {
            $classNameElements[0] = TOBY_ROOT;

            $path = strtolower(implode('/', $classNameElements))."/$className.class.php";

            if(is_file($path)) require_once($path);
            else
            {
                $path = $namespacePath.strtolower(implode('/', $classNameElements)).'/'.strtolower($classNameBase)."/$className.class.php";
                if(is_file($path)) require_once($path);
            }
        }

        // resolve app related
        else
        {
            if($namespacePath !== '') array_unshift($classNameElements, $namespacePath);
            array_unshift($classNameElements, APP_ROOT);

            $path = strtolower(implode('/', $classNameElements))."/$className.class.php";

            if(is_file($path)) require_once($path);
            else
            {
                $path = $namespacePath.strtolower(implode('/', $classNameElements)).'/'.strtolower($classNameBase)."/$className.class.php";
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