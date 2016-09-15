<?php

namespace Toby;

use Toby\Exceptions\TobyException;
use Toby\Utils\Utils;

class Config
{
    /* variables */
    private $data = [];
    
    /* statics */
    public static $instance = null;
    
    /* constructor */
    function __construct()
    {
        if(self::$instance !== null) throw new TobyException('Config is a singleton and therefor can only be accessed through Config::getInstance()');
        self::$instance = $this;
    }

    /* entry management */

    /**
     * @param $key
     *
     * @return bool
     */
    public function hasKey($key, $strict = true)
    {
        if($strict === true)
        {
            return isset($this->data[$key]);
        }
        else
        {
            $keyLen = strlen($key);
            
            foreach($this->data as $dataKey => $dataValue)
            {
                if(strncmp($dataKey, $key, $keyLen) === 0) return true;
            }
            
            return false;
        }
    }

    /**
     * @param $key
     * @param $value
     */
    public function setValue($key, $value)
    {
        // set
        $this->data[$key] = $value;
    }

    /**
     * @param $key
     *
     * @return mixed
     * @throws TobyException
     */
    public function getValue($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /* import */
    public function readDir($dir)
    {
        $list = scandir($dir);

        foreach($list as $filename)
        {
            if($filename[0] === '.') continue;
            if(preg_match('/\.cfg\.php$/', $filename) === 0) continue;
            
            $filePath = Utils::pathCombine([$dir, $filename]);
            if(!is_readable($filePath)) continue;

            $baseName = trim(substr($filename, 0, strrpos($filename, '.cfg.php')));
            $definitions = require $filePath;

            if(!is_array($definitions)) continue;
            
            foreach($definitions as $key => $value)
            {
                $this->setValue($baseName.'.'.$key, $value);
            }
        }
    }

    /* PHP */
    public function __toString()
    {
        return 'Config';
    }
    
    /* static methods */
    public static function getInstance()
    {
        if(self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public static function has($key, $strict = true)
    {
        return self::getInstance()->hasKey($key, $strict);
    }
    
    public static function get($key)
    {
        return self::getInstance()->getValue($key);
    }

    public static function set($key, $value)
    {
        self::getInstance()->setValue($key, $value);
    }
}