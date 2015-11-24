<?php

namespace Toby;

use Toby\Utils\Utils;
use \InvalidArgumentException;

class Config
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
        if(!is_array($data)) throw new InvalidArgumentException('argument $data is not of type array');

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

    /**
     * @param string $key
     * @param string $datatype
     *
     * @return mixed
     */
    public function getValue($key, $datatype = '')
    {
        // cancellation
        if(empty($key)) return false;
        
        // return parsed
        $value = isset($this->data[$key]) ? $this->data[$key] : null;
        return Utils::parseValue($value, $datatype);
    }

    public function getAllValues()
    {
        return is_array($this->data) ? $this->data : array();
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
        return Utils::parseValue($value, $datatype);
    }

    public function setValue($key, $value)
    {
        // cancellation
        if(!is_string($key)) throw new InvalidArgumentException('argument $key is not of type string');

        // set
        $this->data[$key] = $value;
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

            $filePath = Utils::pathCombine(array($dir, $filename));

            if(is_readable($filePath))
            {
                $name = trim(substr($filename, 0, strrpos($filename, '.cfg.php')));

                $config = self::get($name);

                if(empty($config))
                {
                    self::$instances[$name] = new Config($name, self::getConfigVars($filePath));
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

    /**
     * @param $name
     * @return Config
     */
    public static function get($name)
    {
        // cancellation
        if(empty($name))                    return null;
        if(!is_string($name))               return null;
        if(!isset(self::$instances[$name])) return null;

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