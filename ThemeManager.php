<?php

namespace Toby;

use Toby\Utils\StringUtils;

class ThemeManager
{
    /* public variables */
    public static $themeName;
    
    public static $themePathRoot;
    public static $themeURL;
    
    public static $defaultLayout    = 'default';
    
    /* private variables */
    private static $initialized     = false;
    
    /* initialization */
    public static function init($themeName = null, $functionName = null)
    {
        // compute input
        if(empty($themeName))
        {
            $configThemeName        = Config::get('toby.theme.name');
            $configThemeFunction    = Config::get('toby.theme.function');
            
            if(empty($configThemeName))
            {
                $themeName = 'default';
            }
            else
            {
                $themeName = strtolower($configThemeName);
                if(!empty($configThemeFunction)) $functionName = $configThemeFunction;
            }
        }
        else
        {
            $themeName = strtolower($themeName);
        }

        // init if dir exists
        $themePath      = "themes/$themeName";
        $themePathRoot  = StringUtils::buildPath(array(PUBLIC_ROOT, $themePath));

        if(is_dir($themePathRoot))
        {
            // set theme vars
            self::$themeName        = $themeName;
            self::$themePathRoot    = $themePathRoot;
            self::$themeURL         = StringUtils::buildPath(array(Toby::getInstance()->appURLRelative, $themePath));
            
            // include function
            $functionPathRoot = $themePathRoot.'/'.(empty($functionName) ? $themeName : $functionName).'.php';
            
            if(is_file($functionPathRoot))
            {
                require_once($functionPathRoot);
            }
            
            // return success
            self::$initialized = true;
            return true;
        }
        
        // return false
        self::$initialized = false;
        return false;
    }
    
    public static function setDefaultLayout($layout)
    {
        self::$defaultLayout = $layout;
    }
    
    public static function isInitialized()
    {
        return self::$initialized;
    }
}
