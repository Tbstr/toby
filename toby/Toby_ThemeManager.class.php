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
            else
            {
                $themeName = 'default';
            }
        }
        else
        {
            $themeName = strtolower($themeName);
        }
        
        // init if file exists
        $themePath = PUBLIC_ROOT."/themes/$themeName";
        if(file_exists(APP_ROOT.DS.$themePath))
        {
            self::$themeName = $themeName;
            self::$themePathRoot = APP_ROOT.DS.$themePath;
            self::$themeURL = Toby_Utils::pathCombine(array(APP_URL_REL, $themePath));
            
            $configPath = Toby_Utils::pathCombine(array(self::$themePathRoot, ($configName ? $configName : $themeName))).'.info';
            if(file_exists($configPath))
            {
                self::$themeConfig = Toby_ConfigFileManager::read($configPath, true);
                self::$initialized = true;
                
                return true;
            }
        }
        
        // return false
        self::$initialized = false;
        return false;
    }
    
    public static function initByController(Toby_Controller &$controller)
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
        self::$controller = &$controller;
        
        // return
        return true;
    }
    
    public static function placeHeaderInformation()
    {
        // cancellation
        if(empty(self::$themeConfig))   exit('No theme set.');
        
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
