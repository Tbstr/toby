<?php

class Toby_Config
{
    /* static variables */
    public static $instances = array();

    /* variables */
    public $name        = false;
    public $data        = false;
    
    /* constructor */
    function __construct($name, $data)
    {
        // cancellation
        if(empty($name))        return;
        if(empty($data))        return;
        if(!is_array($data))    return;

        // set vars
        $this->name = $name;
        $this->data = $data;
    }

    public function addData($data)
    {
        // cancellation
        if(empty($data))        return false;
        if(!is_array($data))    return false;

        // add
        $this->data = array_merge($this->data, $data);
    }

    /* methods */
    public function hasKey($key)
    {
        // cancellation
        if(empty($key)) return false;

        // check
        return isset($this->data[$key]);
    }
    
    public function getValue($key, $datatype = '')
    {
        // cancellation
        if(empty($key)) return false;
        
        // return parsed
        $value = isset($this->data[$key]) ? $this->data[$key] : null;
        return Toby_Utils::parseValue($value, $datatype);
    }

    public function getValueFromArray($key, $arrayKey, $datatype = '')
    {
        // cancellation
        if(empty($key)) return false;

        // get array
        $array = self::getValue($key);
        if(!is_array($array)) return null;

        // return parsed value
        $value = isset($array[$arrayKey]) ? $array[$arrayKey] : null;
        return Toby_Utils::parseValue($value, $datatype);
    }
    
    /* PHP */
    public function __toString()
    {
        return 'Toby_Config['.$this->name.']';
    }

    /* statics */
    public static function readDir($dir)
    {
        $list = scandir($dir);

        foreach($list as $filename)
        {
            if($filename[0] === '.') continue;
            if(preg_match('/\.cfg\.php$/', $filename) === 0) continue;

            $filePath = Toby_Utils::pathCombine(array($dir, $filename));

            if(is_readable($filePath))
            {
                $name = trim(substr($filename, 0, strrpos($filename, '.cfg.php')));

                $config = self::get($name);

                if(empty($config))
                {
                    self::$instances[$name] = new Toby_Config($name, self::getConfigVars($filePath));
                }
                else
                {
                    $config->addData(self::getConfigVars($filePath));
                }
            }
        }

        return true;
    }

    public static function listConfigs()
    {
        $list = array();
        foreach(self::$instances as $instance) $list[] = $instance->name;

        return $list;
    }

    public static function get($name)
    {
        // cancellation
        if(empty($name))                    return false;
        if(!is_string($name))               return false;
        if(!isset(self::$instances[$name])) return false;

        // return
        return self::$instances[$name];
    }

    private static function getConfigVars($configFilePath)
    {
        include $configFilePath;
        unset($configFilePath);

        return get_defined_vars();
    }
}