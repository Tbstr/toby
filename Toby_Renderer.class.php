<?php

class Toby_Renderer
{
    /* static variables */
    private static $defaultLayoutsPath = '/layout';
    private static $defaultViewsPath = '/view';

    /* static methods */
    public static function renderPage(Toby_Controller $controller)
    {
        // auto init theme manager
        self::themeManagerAutoInit();

        // script
        $content = self::renderView($controller->getViewScript(), get_object_vars($controller->view));
        
        // layout
        $layoutPath = self::findLayout($controller->layoutName);
        if($layoutPath ===  false) Toby::finalize('Toby_Layout "'.$controller->layoutName.'" could not be found.');
        
        $layout = new Toby_Layout($layoutPath, get_object_vars($controller->layout));
        
        $layout->title = $controller->layoutTitle;
        $layout->jsVars = get_object_vars($controller->javascript);
        
        $layout->bodyId = $controller->layoutBodyId;
        $layout->bodyClass = $controller->layoutBodyClasses;
        
        $layout->content = $content;
        
        // render & return
        return $layout->render();
    }
    
    public static function renderView($scriptName, $vars = null)
    {
        // auto init theme manager
        self::themeManagerAutoInit();
        
        // get view script
        $scriptPath = self::findViewScript($scriptName);
        if($scriptPath === false) Toby::finalize('Script "'.$scriptName.'" could not be found.');
        
        // render & return
        $scriptView = new Toby_View($scriptPath, $vars);
        return $scriptView->render();
    }
    
    public static function findLayout($layoutName)
    {
        // theme
        $layoutPath = Toby_ThemeManager::$themePathRoot.'/layout/'.$layoutName.'.tpl.php';
        if(file_exists($layoutPath)) return $layoutPath;
            
        // app
        $layoutPath = APP_ROOT.self::$defaultLayoutsPath.DS.$layoutName.'.tpl.php';
        if(file_exists($layoutPath)) return $layoutPath;
        
        return false;
    }
    
    public static function findViewScript($scriptName)
    {
        // theme
        $viewScriptPath = Toby_ThemeManager::$themePathRoot.'/view/'.$scriptName.'.tpl.php';
        if(file_exists($viewScriptPath)) return $viewScriptPath;
            
        // app
        $viewScriptPath = APP_ROOT.self::$defaultViewsPath.DS.$scriptName.'.tpl.php';
        if(file_exists($viewScriptPath)) return $viewScriptPath;
        
        return false;
    }
    
    private static function themeManagerAutoInit()
    {
        // cancellation
        if(Toby_ThemeManager::$initialized) return;
        if(!Toby_ThemeManager::init()) Toby::finalize('unable to init theme manager');
    }
}