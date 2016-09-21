<?php

namespace Toby;

use Toby\Assets\Assets;
use Toby\Utils\Utils;

class ThemeManager
{
    /* public variables */
    public static $themeName;
    
    public static $themePathRoot;
    public static $themeURL;
    
    public static $defaultLayout   = 'default';
    
    /* private variables */
    private static $controller      = null;
    private static $initialized     = false;
    private static $cache           = [];
    
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
        // vars
        $theme          = null;
        $themeFunction  = null;
        
        // grab from controller
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
    
    /* getter & setter */
    public static function setDefaultLayout($layoutName)
    {
        self::$defaultLayout = $layoutName;
    }
    
    public static function isInitialized()
    {
        return self::$initialized;
    }
    
    /* placements */
    private static function getSets()
    {
        // get from cache
        if(isset(self::$cache['sets'])) return self::$cache['sets'];
        
        // get
        /** @var \Toby\Assets\AssetsSet[] $sets */
        $sets = array_merge(Assets::getStandardSets(), Assets::getSetsByResolvePath(Toby::getInstance()->resolve));
        
        // put to cache
        self::$cache['sets'] = $sets;
        
        // return
        return $sets;
    }
    
    public static function placeScripts()
    {
        $sets = self::getSets();
        
        foreach($sets as $set)
        {
            echo implode("\n", $set->buildDOMElementsJavaScript())."\n";
        }
    }

    public static function placeStyles()
    {
        $sets = self::getSets();
        
        foreach($sets as $set)
        {
            echo implode("\n", $set->buildDOMElementsCSS())."\n";
        }
    }
}
