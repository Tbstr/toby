<?php

class Toby_Config
{
    public static $instance = null;
    
    /* static getter */
    public static function getInstance()
    {
        if(self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    
    /* ststic shortcuts */
    public static function _hasKey($config, $key)
    {
        return self::$instance->hasKey($config, $key);
    }
    
    public static function _getValue($config, $key, $datatype = '')
    {
        return self::$instance->getValue($config, $key, $datatype);
    }
    
    public static function _getConfig($config)
    {
        if(!isset(self::$instance->$config)) return null;
        return self::$instance->$config;
    }
    
    /* methods */
    public function readDir($dir)
    {
        $list = scandir($dir);
        
        foreach($list as $filename)
        {
            if($filename[0] == '.') continue;
            if(preg_match('/\.cfg\.php$/', $filename) === 0) continue;

            $filePath = "$dir/$filename";

            if(is_readable($filePath))
            {
                $key = substr($filename, 0, strrpos($filename, '.cfg.php'));
                
                if(isset($this->$key))
                {
                    $this->$key = array_merge($this->$key, $this->getConfigVars($filePath));
                }
                else
                {
                    $this->$key = $this->getConfigVars($filePath);
                }
            }
        }
        
        return $this;
    }
    
    private function getConfigVars($configFilePath)
    {
        include $configFilePath;
        unset($configFilePath);
        
        return get_defined_vars();
    }
    
    public function hasKey($config, $key)
    {
        if(!isset($this->$config)) Toby::finalize("config '$config' does not exist");
        
        $configLink = $this->$config;
        return isset($configLink[$key]);
    }
    
    public function getValue($config, $key, $datatype = '')
    {
        // cancellate
        if(!isset($this->$config)) Toby::finalize("config '$config' does not exist");
        
        $configLink = &$this->$config;
        
        // return parsed
        $value = isset($configLink[$key]) ? $configLink[$key] : null;
        return Toby_Utils::parseValue($value, $datatype);
    }
    
    /* to string */
    public function __toString()
    {
        return 'Toby_Config';
    }
}