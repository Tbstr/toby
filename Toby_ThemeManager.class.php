<?php

class Toby_ThemeManager
{
    /* variables */
    public static $themeName;
    
    public static $themePathRoot;
    public static $themeURL;
    
    public static $themeConfig;
    private static $controller;
    
    public static $initialized      = false;
    
    public static function init($themeName = false, $configName = false)
    {
        // compute input
        if($themeName === false)
        {
            $configThemeName    = Toby_Config::_getValue('toby', 'theme');
            $configThemeConfig  = Toby_Config::_getValue('toby', 'themeConfig');
            
            if(!empty($configThemeName))
            {
                $themeName = $configThemeName;
                if(!empty($configThemeConfig)) $configName = $configThemeConfig;
            }
            else $themeName = 'default';
        }
        else $themeName = strtolower($themeName);
        
        // init if file exists
        $themePath = "themes/$themeName";
        
        if(file_exists(PUBLIC_ROOT.DS.$themePath))
        {
            self::$themeName        = $themeName;
            self::$themePathRoot    = PUBLIC_ROOT.DS.$themePath;
            self::$themeURL         = Toby_Utils::pathCombine(array(Toby::getInstance()->appURLRelative, $themePath));
            
            if(self::loadConfig($configName) === false)
            {
                self::$initialized = false;
                return false;
            }
            
            // return success
            self::$initialized = true;
            return true;
        }
        
        // return false
        self::$initialized = false;
        return false;
    }
    
    public static function initByController(Toby_Controller $controller)
    {
        // vars
        $theme = false;
        $themeConfig = false;
        
        // grab from controller
        if(isset($controller->themeOverride))
        {
            $theme          = $controller->themeOverride;
            $themeConfig    = $controller->themeConfigOverride;
        }
        
        // set
        if(!self::init($theme, $themeConfig)) return false;
        self::$controller   = $controller;
        
        // return
        return true;
    }
    
    private static function loadConfig($configName)
    {
        $configPath = Toby_Utils::pathCombine(array(self::$themePathRoot, ($configName !== false ? $configName : self::$themeName))).'.info';
        $shmop = Toby_Utils::extensionLoaded('shmop');
        
        if(file_exists($configPath))
        {
            // read from memcache
            $dataFromCache = false;
            
            if($shmop === true)
            {
                $key = ftok($configPath, 't');
                $shmId = @shmop_open($key , 'a', 0644 , 0);

                if($shmId !== false)
                {
                    $cacheData = unserialize(shmop_read($shmId, 0, shmop_size($shmId)));
                    
                    if($cacheData['time'] > filemtime($configPath))
                    {
                        self::$themeConfig = $cacheData['data'];
                        
                        $dataFromCache = true;
                        shmop_close($shmId);
                    }
                    else
                    {
                        shmop_delete($shmId);
                        shmop_close($shmId);
                    }
                }
            }

            // parse from filesystem and cache
            if($dataFromCache === false)
            {
                // read & parse
                self::$themeConfig = self::readConfig($configPath, true);

                // cache
                if($shmop === true)
                {
                    $cacheData = array(
                        'time' => time(),
                        'data' => self::$themeConfig
                    );
                    $serialized = serialize($cacheData);
                    
                    $shmId = @shmop_open($key , 'c', 0644 , strlen($serialized));
                    if($shmId !== false)
                    {
                        shmop_write($shmId, $serialized, 0);
                        shmop_close($shmId);
                    }
                }
            }
            
            // return
            return true;
        }
        
        // return
        return false;
    }
    
    public static function placeHeaderInformation()
    {
        // cancellation
        if(empty(self::$themeConfig))   Toby::finalize('No theme set.');
        
        // vars
        $controllerAvailable    = !empty(self::$controller);
        $actionAvailable        = $controllerAvailable && !empty(self::$controller->action);
        $attributesAvailable    = $actionAvailable && !empty(self::$controller->attributes);
        
        // set groups
        $groups = array();
        if($controllerAvailable)
        {
            $groupsStr = '';
            
            // controller
            $groupsStr = self::$controller->name;
            $groups[] = $groupsStr;
            
            // action
            if($actionAvailable)
            {
                $groupsStr .= '/'.self::$controller->action;
                $groups[] = $groupsStr;
                
                // attributes
                if($attributesAvailable)
                {
                    foreach(self::$controller->attributes as $attribute)
                    {
                        $groupsStr .= '/'.$attribute;
                        $groups[] = $groupsStr;
                    }
                }
            }
        }
        
        // gather links
        $links = isset(self::$themeConfig['global']) ? self::$themeConfig['global'] : array();
        
        $groupSet = false;
        foreach($groups as $group)
        {
            if(isset(self::$themeConfig[$group]))
            {
                $links = array_merge_recursive($links, self::$themeConfig[$group]);
                $groupSet = true;
            }
        }
        
        if(!$groupSet)
        {
            if(isset(self::$themeConfig['default'])) $links = array_merge_recursive($links, self::$themeConfig['default']);
        }
        
        if($controllerAvailable)
        {
            $controllerLinks = array();
            
            if(!empty(self::$controller->layoutStyles))     $controllerLinks['stylesheets'] = self::$controller->layoutStyles;
            if(!empty(self::$controller->layoutScripts))    $controllerLinks['javascripts'] = self::$controller->layoutScripts;
            
            if(!empty($controllerLinks)) $links = array_merge_recursive($links, $controllerLinks);
        }
        
        // set version
        $versionStr = isset(self::$themeConfig['global']['versionQuery']) ? '?v='.self::$themeConfig['global']['versionQuery'] : '';
        
        // generate styles
        if(isset($links['stylesheets']))
        {
            foreach($links['stylesheets'] as $media => $stylePaths)
            {
                foreach($stylePaths as $stylePath)
                {
                    if(preg_match('/^(https?:\/\/|\/)/', $stylePath))
                    {
                        echo "<link rel=\"stylesheet\" type=\"text/css\" media=\"$media\" href=\"{$stylePath}{$versionStr}\" />\n";
                    }
                    else
                    {
                        echo "<link rel=\"stylesheet\" type=\"text/css\" media=\"$media\" href=\"".self::$themeURL.DS.$stylePath.$versionStr."\" />\n";
                    }
                }
            }
        }
        
        // generate javascripts
        if(isset($links['javascripts']))
        {
            foreach($links['javascripts'] as $scriptPath)
            {
                if(preg_match('/^(https?:\/\/|\/)/', $scriptPath))
                {
                    echo "<script type=\"text/javascript\" src=\"{$scriptPath}{$versionStr}\"></script>\n";
                }
                else
                {
                    echo "<script type=\"text/javascript\" src=\"".self::$themeURL.DS.$scriptPath.$versionStr."\"></script>\n";
                }
            }
        }
    }
    
    /* config parsing */
    public static function readConfig($filePath, $processSections = false)
    {
        if(file_exists($filePath))
        {
            if(($handle = @fopen($filePath, 'r')) === false) return false;
            
            $data = array();
            
            if($processSections)
            {
                $data['global'] = array();
                $dataCursor = &$data['global'];
            }
            else $dataCursor = &$data;
            
            while(($line = fgets($handle)) !== false)
            {
                // trim line
                $line = trim($line);
                
                // continue if commented or empty
                if(strlen($line) === 0) continue;
                if($line[0] === '#') continue;
                
                // proove for section marker
                if($line[0] === '[' && $line[strlen($line)-1] === ']')
                {
                    if($processSections)
                    {
                        $sectionName = substr($line, 1, strlen($line) - 2);
                        $data[$sectionName] = array();
                        $dataCursor = &$data[$sectionName];
                    }
                    
                    continue;
                }
                
                // get line parts
                list($keys, $value) = explode('=', $line, 2);
                $keys = trim($keys);
                $value = self::readConfigElement(trim($value));
                
                // get key parts
                $counter = 0;
                $buffer = '';
                $parts = array();
                
                // get keys
                if(strpos($keys, '[') === false) array_push($parts, $keys);
                else
                {
                    while($counter < strlen($keys))
                    {
                        switch($line[$counter])
                        {
                            case '[':
                                if(count($parts) === 0)
                                {
                                    array_push($parts, trim($buffer));
                                    $buffer = '';
                                }
                                break;

                            case ']':
                                array_push($parts, trim($buffer));
                                $buffer = '';
                                break;

                            default:
                                $buffer .= $line[$counter];
                                break;

                        }

                        $counter++;
                    }
                }
                
                // apply key parts
                $counter = 0;
                $keydataCursor = &$dataCursor;
                foreach($parts as $part)
                {
                    $counter++;
                    $last = $counter == count($parts);

                    if($part === '')
                    {
                        $index = array_push($keydataCursor, ($last ? $value : array()));
                        $keydataCursor = &$keydataCursor[$index - 1];
                    }
                    else
                    {
                        if(!isset($keydataCursor[$part]))
                        {
                            $keydataCursor[$part] = ($last ? $value : array());
                        }

                        $keydataCursor = &$keydataCursor[$part];
                    }
                }
            }
            
            fclose($handle);
            return $data;
        }
        
        return false;
    }
    
    private static function readConfigElement($element)
    {
        // boolean
        $toLower = strtolower($element);
        if($toLower === 'true' || $toLower === 'false') return $toLower === 'true';
        
        // number
        if(is_numeric($element))
        {
            if(strpos($element, '.') === false) return (int)$element;
            else return (float)$element;
        }
        
        // else
        return $element;
    }
}
