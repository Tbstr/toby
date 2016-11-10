<?php

namespace Toby;

use \InvalidArgumentException;

class Autoloader
{
    /* variables */
    private static $classes    = array();
    private static $namespaces = array();

    private static $controllerNamespace = 'Controller';

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
            $nsLen = strlen($namespace);
            
            if(strncmp($classPath, $namespace, $nsLen) === 0)
            {
                if($classPath[$nsLen] === '\\')
                {
                    return $path.'/'.str_replace('\\', '/', substr($classPath, $nsLen + 1));
                }
            }
        }

        return $classPath;
    }

    /* controller loading */
    public static function hasControllerInstance($controllerName)
    {
        // build class name
        $controllerClassName = self::getControllerClassName($controllerName);
        
        // check
        if(class_exists($controllerClassName, false))
        {
            return true;
        }
        else
        {
            self::load($controllerClassName);
            if(class_exists($controllerClassName, false))
            {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @param string $controllerName
     *
     * @return Controller|null
     */
    public static function getControllerInstance($controllerName)
    {
        // build class name
        $controllerClassName = self::getControllerClassName($controllerName);

        // load
        if(!class_exists($controllerClassName, false))
        {
            self::load($controllerClassName);
            if(!class_exists($controllerClassName, false)) return null;
        }

        // instantiate & return
        return new $controllerClassName($controllerName);
    }

    private static function getControllerClassName($controllerName)
    {
        return self::$controllerNamespace.'\\'.strtoupper($controllerName[0]).substr($controllerName, 1).'Controller';
    }
    
    public static function setControllerNamespace($newControllerNamespace)
    {
        // cancellation
        if(!is_string($newControllerNamespace) || empty($newControllerNamespace))  throw new InvalidArgumentException('argument $newControllerNamespace is not of type string or empty');

        // set
        self::$controllerNamespace = trim($newControllerNamespace, ' \\');
    }

    /* load */
    public static function load($className)
    {
        // check if already loaded
        if(class_exists($className, false)) return;

        // LEVEL 1: registered classes
        if(isset(self::$classes[$className]))
        {
            require_once self::$classes[$className];
        }

        // LEVEL 2: PSR-4
        $classPath = self::resolveNamespace($className);
        if(is_file($classPath.'.php'))
        {
            require_once $classPath.'.php';
        }
    }
}
