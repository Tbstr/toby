<?php

namespace Toby;

use Toby\Utils\Utils;

class ThemeManager
{
    /* public variables */
    public static $themeName;
    
    public static $themePathRoot;
    public static $themeURL;
    
    public static $defaultLayout    = 'default';
    
    /* private variables */
    private static $controller      = null;
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
        $themePathRoot  = Utils::pathCombine(array(PUBLIC_ROOT, $themePath));

        if(is_dir($themePathRoot))
        {
            // set theme vars
            self::$themeName        = $themeName;
            self::$themePathRoot    = $themePathRoot;
            self::$themeURL         = Utils::pathCombine(array(Toby::getInstance()->appURLRelative, $themePath));
            
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
    
    public static function initByController(Controller $controller)
    {
        // theme
        $theme          = null;
        $themeFunction  = null;
        
        if(isset($controller->overrides['theme']))
        {
            $theme          = $controller->overrides['theme'];
            $themeFunction  = isset($controller->overrides['theme_function']) ? $controller->overrides['theme_function'] : null;
        }

        // set
        if(!self::init($theme, $themeFunction)) return false;
        self::$controller = $controller;

        // return
        return true;
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
