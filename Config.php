<?php

namespace Toby;

use Toby\Utils\Utils;

class Config
{
    /* variables */
    private $data = [];
    
    /* statics */
    public static $instance = null;

    /* entry management */

    /**
     * @param string $key
     * @param bool   $strict
     *
     * @return bool
     */
    public function hasEntry($key, $strict = true)
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
     * @param string $key
     * @param mixed  $value
     */
    public function setEntry($key, $value)
    {
        // set
        $this->data[$key] = $value;
    }

    /**
     * @param string $keyBase
     * @param array $arr
     */
    public function setEntriesFromArray($keyBase, array $arr)
    {
        foreach($arr as $key => $value)
        {
            if(is_array($value))
            {
                $this->setEntriesFromArray($keyBase.'.'.$key, $value);
            }
            else
            {
                $this->data[$keyBase] = $value;
            }
        }
    }

    /**
     * @param string $key
     */
    public function removeEntry($key)
    {
        unset($this->data[$key]);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getValue($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * @param string $keyBase
     *
     * @return array
     */
    public function getSubValues($keyBase)
    {
        // append delimiter
        $keyBase .= '.';
        
        // vars
        $keyBaseLength = strlen($keyBase);
        $subValues = [];
        
        // search & pack
        foreach($this->data as $key => $value)
        {
            if(strncmp($key, $keyBase, $keyBaseLength) === 0)
            {
                $this->packSubValue($subValues, substr($key, $keyBaseLength), $value);
            }
        }

        // return
        return $subValues;
    }

    /**
     * @param array  $data
     * @param string $key
     * @param mixed  $value
     */
    private function packSubValue(array &$data, $key, $value)
    {
        $keyElements = explode('.', $key);
        $dataCursor = &$data;
        
        for($i = 0, $le = count($keyElements) - 1; $i <= $le; $i++)
        {
            $ke = $keyElements[$i];
            
            if($i === $le)
            {
                $dataCursor[$ke] = $value;
            }
            else
            {
                if(!isset($dataCursor[$ke])) $dataCursor[$ke] = [];
                $dataCursor = &$dataCursor[$ke];
            }
        }
    }

    /* import */

    /**
     * @param string $dir
     */
    public function readDir($dir)
    {
        $list = scandir($dir);

        foreach($list as $filename)
        {
            if($filename[0] === '.') continue;

            $filePath = Utils::pathCombine([$dir, $filename]);
            if(!is_readable($filePath)) continue;
            
            // php
            if(preg_match('/\.cfg\.php$/', $filename) === 1)
            {
                $baseName = trim(substr($filename, 0, strrpos($filename, '.cfg.php')));
                $definitions = require $filePath;

                if(!is_array($definitions)) continue;

                foreach($definitions as $key => $value)
                {
                    $this->setEntry($baseName.'.'.$key, $value);
                }
            }

            // yaml
            /*
            elseif(preg_match('/\.cfg\.yml$/', $filename) === 1)
            {
                $baseName = trim(substr($filename, 0, strrpos($filename, '.cfg.yml')));

                $definitions = yaml_parse_file($filePath);
                $this->setEntriesFromArray($baseName, $definitions);
            }
            */
        }
    }
    
    /* clone */

    /**
     * @return Config
     */
    public function getClone()
    {
        $clone = new self();

        foreach($this->data as $key => $value)
        {
            $clone->setEntry($key, $value);
        }
        
        return $clone;
    }
    
    /* PHP */
    public function __toString()
    {
        return 'Config';
    }
    
    /* static methods */

    /**
     * @return Config
     */
    public static function getInstance()
    {
        if(self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /**
     * @param string $key
     * @param bool   $strict
     *
     * @return bool
     */
    public static function has($key, $strict = true)
    {
        return self::getInstance()->hasEntry($key, $strict);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function get($key)
    {
        return self::getInstance()->getValue($key);
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function set($key, $value)
    {
        self::getInstance()->setEntry($key, $value);
    }
}