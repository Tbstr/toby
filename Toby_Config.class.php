<?php

class Toby_Config
{
    public static $instance = null;
    
    /* constructor */
    function __construct()
    {
        if(self::$instance === null) self::$instance  = $this;
        else throw new Exception('Toby_Config is a singleton and therefore can only be accessed through Toby_Config::getInstance().');
    }
    
    /* static getter */
    public static function getInstance()
    {
        if(self::$instance === null) new self();
        return self::$instance;
    }
    
    /* static shortcuts */
    public static function _hasKey($config, $key)
    {
        return self::getInstance()->hasKey($config, $key);
    }
    
    public static function _getValue($config, $key, $datatype = '')
    {
        return self::getInstance()->getValue($config, $key, $datatype);
    }
    
    public static function _getConfig($config)
    {
        if(!isset(self::getInstance()->$config)) return null;
        return self::getInstance()->$config;
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