<?php

namespace Toby;

class Renderer
{
    /* static variables */
    private static $defaultLayoutsPath = '/layout';
    private static $defaultViewsPath = '/view';

    /* static methods */
    public static function renderPage(Controller $controller)
    {
        // auto init theme manager
        self::themeManagerAutoInit();

        // script
        $content                = self::renderView($controller->getViewScript(), get_object_vars($controller->view));
        
        // layout
        $layoutPath             = self::findLayout($controller->layoutName);
        if($layoutPath === null) Toby::finalize('Toby_Layout "'.$controller->layoutName.'" could not be found.');
        
        $layout                 = new Layout($layoutPath, get_object_vars($controller->layout));
        
        $layout->title          = $controller->layoutTitle;
        $layout->jsVars         = get_object_vars($controller->javascript);
        
        $layout->setBodyId($controller->layoutBodyId);
        $layout->addBodyClass($controller->layoutBodyClasses);
        
        $layout->setContent($content);

        // render & return
        return $layout->render();
    }
    
    public static function renderView($scriptName, $vars = null)
    {
        // auto init theme manager
        self::themeManagerAutoInit();
        
        // get view script
        $scriptPath = self::findViewScript($scriptName);
        if($scriptPath === null) Toby::finalize('Script "'.$scriptName.'" could not be found.');
        
        // render & return
        $scriptView = new View($scriptPath, $vars);
        return $scriptView->render();
    }
    
    public static function findLayout($layoutName)
    {
        // theme
        $layoutPath = ThemeManager::$themePathRoot.'/layout/'.$layoutName.'.php';
        if(is_file($layoutPath)) return $layoutPath;
            
        // app
        $layoutPath = APP_ROOT.self::$defaultLayoutsPath.DS.$layoutName.'.php';
        if(is_file($layoutPath)) return $layoutPath;
        
        return null;
    }
    
    public static function findViewScript($scriptName)
    {
        // theme
        $viewScriptPath = ThemeManager::$themePathRoot.'/view/'.$scriptName.'.php';
        if(is_file($viewScriptPath)) return $viewScriptPath;
            
        // app
        $viewScriptPath = APP_ROOT.self::$defaultViewsPath.DS.$scriptName.'.php';
        if(is_file($viewScriptPath)) return $viewScriptPath;
        
        return null;
    }
    
    private static function themeManagerAutoInit()
    {
        // cancellation
        if(ThemeManager::$initialized) return;
        if(!ThemeManager::init()) Toby::finalize('unable to init theme manager');
    }
}