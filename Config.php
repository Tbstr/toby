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
     * @param array  $hierarchy
     */
    public function setEntry($key, $value, array $hierarchy = null)
    {
        $hierarchyStr = empty($hierarchy) ? null : implode('.', $hierarchy);
        $fullKey = ($hierarchyStr === null ? '' : $hierarchyStr.'.').$key;
        
        if($hierarchyStr === null)
        {
            $arr = &$this->data;
        }
        else
        {
            if(isset($this->dataIndex[$hierarchyStr]))
            {
                if(is_array($this->dataIndex[$hierarchyStr]))
                {
                    $arr = &$this->dataIndex[$hierarchyStr];
                }
                else
                {
                    $subKey = array_pop($hierarchy);
                    $this->setEntry($subKey, [], $hierarchy);

                    $arr = &$this->dataIndex[$hierarchyStr];
                }
            }
            else
            {
                $subKey = array_pop($hierarchy);
                $this->setEntry($subKey, [], $hierarchy);
                
                $arr = &$this->dataIndex[$hierarchyStr];
            }
        }
        
        // remove old data from index
        if (isset($this->dataIndex[$fullKey]) && is_array($this->dataIndex[$fullKey]))
        {
            $this->removeFromIndex($fullKey);
        }
        $arr[$key] = $value;

        $this->dataIndex[$fullKey] = &$arr[$key];
    }

    private function removeFromIndex($key)
    {
        if (isset($this->dataIndex[$key]) && is_array($this->dataIndex[$key]))
        {
            foreach ($this->dataIndex[$key] as $k => $v)
            {
                $this->removeFromIndex($key . '.' . $k);
            }
        }
        unset($this->dataIndex[$key]);
    }

    /**
     * @param array $arr
     * @param array $hierarchy
     */
    public function setEntriesFromArray(array $arr, array $hierarchy = null)
    {
        foreach($arr as $key => &$value)
        {
            if(is_array($value))
            {
                $this->setEntriesFromArray($value, empty($hierarchy) ? [$key] : array_merge($hierarchy, [$key]));
            }
            else
            {
                $this->setEntry($key, $value, $hierarchy);
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
        
        if (isset($this->dataIndex[$key]) && is_array($this->dataIndex[$key]))
        {
            $this->removeFromIndex($key);
        }
        unset($this->dataIndex[$key]);
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
        // scan directory
        $filenames = scandir($dir);
        foreach($filenames as $filename)
        {
            // omit filenames starting with colon
            if($filename[0] === '.') continue;
            
            // assemble path & pass on
            $filePath = StringUtils::buildPath([$dir, $filename]);
            $this->readFile($filePath);
        }
    }
    
    public function readFile($filePath)
    {
        // check readability
        if(!is_readable($filePath)) return;

        // get filename
        $filename = basename($filePath);
        
        // disassemble filename
        $colonIndex = strrpos($filename, '.');
        if($colonIndex === false) return;

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
                if(!is_array($definitions)) return;

                // set entries
                $this->setEntriesFromArray($definitions, [$basename]);
            }

            // yaml
            elseif($ext === 'yml')
            {
                // load & parse definitions
                $definitions = Yaml::parse(file_get_contents($filePath));

                // set entries
                $this->setEntriesFromArray($definitions, [$basename]);
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
            $this->setEntriesFromArray($cachedData, [$basename]);
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
        $clone-> setEntriesFromArray($this->data);
        
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
     * @param array  $hierarchy
     */
    public static function set($key, $value, array $hierarchy = null)
    {
        self::getInstance()->setEntry($key, $value, $hierarchy);
    }
}
