<?php

namespace Toby;

use Toby\Assets\Assets;
use Toby\Utils\Utils;

class ThemeManager
{
    /* variables */
    public static $themeName;
    
    public static $themePathRoot;
    public static $themeURL;
    
    private static $controller;
    
    public static $initialized      = false;
    
    public static function init($themeName = false, $functionName = false)
    {
        // compute input
        if($themeName === false)
        {
            $configThemeName        = Config::get('toby')->getValue('theme');
            $configThemeFunction    = Config::get('toby')->getValue('themeFunction');
            
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
        else $themeName = strtolower($themeName);

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
            $functionPathRoot = $themePathRoot.'/'.($functionName ? $functionName : $themeName).'.cfg.php';
            if(is_file($functionPathRoot)) require_once($functionPathRoot);

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
        $theme          = false;
        $themeFunction  = false;
        
        // grab from controller
        if(isset($controller->themeOverride))
        {
            $theme          = $controller->themeOverride;
            $themeFunction  = $controller->themeFunctionOverride;
        }

        // set
        if(!self::init($theme, $themeFunction)) return false;
        self::$controller = $controller;

        // return
        return true;
    }
    
    public static function placeHeaderInformation()
    {

        // sets
        /** @var \Toby\Assets\AssetsSet[] $sets */
        $sets = array_merge(Assets::getStandardSets(), Assets::getSetsByResolvePath(Toby::getInstance()->resolve));

        // css
        foreach($sets as $set)
        {
            echo implode("\n", $set->buildDOMElementsCSS());
            echo "\n";
        }

        // js
        foreach($sets as $set)
        {
            echo implode("\n", $set->buildDOMElementsJavaScript());
            echo "\n";
        }
    }
}
