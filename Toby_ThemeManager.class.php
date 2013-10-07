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
            self::$themeURL         = Toby_Utils::pathCombine(array(APP_URL_REL, $themePath));
            
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
            $theme = $controller->themeOverride;
            $themeConfig = $controller->themeConfigOverride;
        }
        
        // set
        if(!self::init($theme, $themeConfig)) return false;
        self::$controller = $controller;
        
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
                self::$themeConfig = Toby_ConfigFileManager::read($configPath, true);

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
        
        // set groups
        $groups = array();
        if(!empty(self::$controller))
        {
            array_push($groups, self::$controller->name);
            if(!empty(self::$controller->action)) array_push($groups, self::$controller->name.'/'.self::$controller->action);
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
            if(isset(self::$themeConfig['nogroup'])) $links = array_merge_recursive($links, self::$themeConfig['nogroup']);
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
                    if(preg_match('/https?:\/\/.*/', $stylePath))
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
                if(preg_match('/https?:\/\/.*/', $scriptPath))
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
}
