<?php

namespace Toby;

use \InvalidArgumentException;

class Autoloader
{
    /* variables */
    private static $classes     = array();
    private static $namespaces  = array();

    /* class management */
    public static function addClass($className, $pathToFile)
    {
        // check for existing entry
        if(isset(self::$classes[$className])) throw new \Exception("class $className is already registered");

        // create entry
        self::$classes[$className] = $pathToFile;
    }

    public static function addClasses($classes)
    {
        // cancellation
        if(!is_array($classes)) throw new InvalidArgumentException('argument $classes is not of type array');

        // add
        foreach($classes as $className => $path) self::addClass($className, $path);
    }

    /* namespace management */
    public static function addNamespace($namespace, $path)
    {
        // cancellation
        if(!is_string($namespace) || empty($namespace)) throw new InvalidArgumentException('argument $namespace is not of type string or empty');
        if(!is_string($path) || empty($path)) throw new InvalidArgumentException('argument $path is not of type string or empty');

        // normalize input
        $namespace = trim($namespace, '\\');
        $path = rtrim($path, '/');

        // check for existing entry
        if(isset(self::$namespaces[$namespace])) throw new InvalidArgumentException("namespace $namespace is already registered");

        // create entry
        self::$namespaces[$namespace] = $path;
    }

    public static function addNamespaces($namespaces)
    {
        // cancellation
        if(!is_array($namespaces)) throw new InvalidArgumentException('argument $namespaces is not of type array');

        // add
        foreach($namespaces as $namespace => $path) self::addNamespace($namespace, $path);
    }

    public static function resolveNamespace($classPath)
    {
        // normalize input
        $classPath = trim($classPath, '\\');

        // resolve
        foreach(self::$namespaces as $namespace => $path)
        {
            if(strncmp($classPath, $namespace, strlen($namespace)) === 0)
            {
                return $path.'/'.str_replace('\\', '/', substr($classPath, strlen($namespace) + 1));
            }
        }

        return $classPath;
    }

    /* load */
    public static function load($className)
    {
        // LEVEL 1: registered classes
        if(isset(self::$classes[$className]))
        {
            require_once self::$classes[$className];
            return;
        }

        // LEVEL 2: PSR-4
        $classPath = self::resolveNameSpace($className);
        if(is_file($classPath.'.php'))
        {
            require_once $classPath.'.php';
            return;
        }
    }
}