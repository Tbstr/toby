<?php

namespace Toby;

use Symfony\Component\Yaml\Yaml;
use Toby\Utils\StringUtils;

class Config
{
    /* variables */
    private $data = [];
    private $dataIndex = [];
    
    /* statics */
    public static $enableFileCache = false;
    
    private static $instance = null;

    /* entry management */

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function setEntry($key, &$value)
    {
        $keyElements = explode('.', $key);
        
        $keyName = array_pop($keyElements);
        $keyBase = implode('.', $keyElements);
        
        if($keyBase === '')
        {
            $arr = &$this->data;
        }
        else
        {
            if(isset($this->dataIndex[$keyBase]))
            {
                if(is_array($this->dataIndex[$keyBase]))
                {
                    $arr = &$this->dataIndex[$keyBase];
                }
                else
                {
                    $newArr = [];
                    $this->setEntry($keyBase, $newArr);

                    $arr = &$this->dataIndex[$keyBase];
                    
                    $this->removeUpperIndices($keyBase);
                }
            }
            else
            {
                $newArr = [];
                $this->setEntry($keyBase, $newArr);
                
                $arr = &$this->dataIndex[$keyBase];
            }
        }

        $arr[$keyName] = $value;
        $this->dataIndex[$key] = &$arr[$keyName];
    }
    
    private function removeUpperIndices($baseKey)
    {
        $baseKeyLength = strlen($baseKey);
        
        foreach($this->dataIndex as $key => $value)
        {
            if(strncmp($key, $baseKey, $baseKeyLength) === 0)
            {
                if(strlen($key) > $baseKeyLength) unset($this->dataIndex[$key]);
            }
        }
    }

    /**
     * @param array  $arr
     * @param string $keyBase
     */
    public function setEntriesFromArray(array &$arr, $keyBase = null)
    {
        foreach($arr as $key => $value)
        {
            $currKey = empty($keyBase) ? $key : $keyBase.'.'.$key;
            
            if(is_array($value))
            {
                $this->setEntriesFromArray($value, $currKey);
            }
            else
            {
                $this->setEntry($currKey, $value);
            }
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasEntry($key)
    {
        return isset($this->dataIndex[$key]);
    }
    
    /**
     * @param string $key
     */
    public function removeEntry($key)
    {
        $keyElements = explode('.', $key);

        $keyName = array_pop($keyElements);
        $keyBase = implode('.', $keyElements);
        
        
        if(empty($keyBase))
        {
            $arr = &$this->data;
        }
        else
        {
            if(isset($this->dataIndex[$keyBase]))
            {
                $arr = &$this->dataIndex[$keyBase];
            }
            else
            {
                return;
            }
        }

        unset($arr[$keyName]);
        
        unset($this->dataIndex[$key]);
        $this->removeUpperIndices($key);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getValue($key)
    {
        return isset($this->dataIndex[$key]) ? $this->dataIndex[$key]: null;
    }

    /* import */

    /**
     * @param string $dir
     */
    public function readDir($dir)
    {
        // scan directory & pase
        $filenames = scandir($dir);
        foreach($filenames as $filename)
        {
            // omit filenames starting with colon
            if($filename[0] === '.') continue;
            
            // assemble path & check file
            $filePath = StringUtils::buildPath([$dir, $filename]);
            if(!is_readable($filePath)) continue;

            // disassemble filename
            $colonIndex = strrpos($filename, '.');
            if($colonIndex === false) continue;

            $basename = strtolower(substr($filename, 0, $colonIndex));
            $ext = strtolower(substr($filename, $colonIndex + 1));
            
            // cache lookup
            $cachedData = self::$enableFileCache ? $this->readFromCache($filePath) : null;
            
            // read & parse file if cache entry missing ...
            if($cachedData === null)
            {
                $definitions = null;
                
                // php
                if($ext === 'php')
                {
                    // load & parse definitions
                    $definitions = require $filePath;
                    if(!is_array($definitions)) continue;
                    
                    // set entries
                    $this->setEntriesFromArray($definitions, $basename);
                }

                // yaml
                elseif($ext === 'yml')
                {
                    // load & parse definitions
                    $definitions = Yaml::parse(file_get_contents($filePath));

                    // set entries
                    $this->setEntriesFromArray($definitions, $basename);
                }
                
                // put to cache
                if(self::$enableFileCache && $definitions !== null)
                {
                    $this->putToCache($filePath, $definitions);
                }
            }
            
            // ... or use cached data
            else
            {
                $this->setEntriesFromArray($cachedData, $basename);
            }
        }
    }

    /**
     * @param string $filePath
     *
     * @return mixed
     */
    private function readFromCache($filePath)
    {
        // get data from cache
        $key = ftok($filePath, 'p');
        $shmId = shmop_open($key, 'a', 0644 , 0);
        $data = null;
        
        if($shmId !== false)
        {
            $cacheData = json_decode(shmop_read($shmId, 0, shmop_size($shmId)), true);
            
            // verify filemtime
            if(isset($cacheData['filemtime']))
            {
                if(filemtime($filePath) > $cacheData['filemtime'])
                {
                    shmop_delete($shmId);
                }
                else
                {
                    $data = $cacheData['data'];
                }
            }
            else
            {
                shmop_delete($shmId);
            }
            
            // close & return
            shmop_close($shmId);
            return $data;
        }
        
        // return on fail
        return null;
    }

    /**
     * @param string $filePath
     * @param array  $data
     */
    private function putToCache($filePath, array $data)
    {
        // pack data
        $cacheData = [
            'filemtime' => filemtime($filePath),
            'data' => $data
        ];
        
        $cacheDataJSON = json_encode($cacheData);
        
        // save to cache
        $key = ftok($filePath, 'p');
        $shmId = shmop_open($key , 'c', 0644 , strlen($cacheDataJSON));
        
        if($shmId !== false)
        {
            shmop_write($shmId, $cacheDataJSON, 0);
            shmop_close($shmId);
        }
    }
    
    /* clone */

    /**
     * @return Config
     */
    public function getClone()
    {
        // instantiate
        $clone = new self();
        
        // set entries
        $clone->setEntriesFromArray($this->data);
        
        // return
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
     *
     * @return mixed
     */
    public static function has($key)
    {
        return self::getInstance()->hasEntry($key);
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
