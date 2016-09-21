<?php

namespace Toby;

use Toby\Assets\Assets;

class Renderer
{
    /* static variables */
    private static $defaultLayoutsPath  = '/layout';
    private static $defaultViewsPath    = '/view';

    /* static methods */
    public static function renderPage(Controller $controller)
    {
        // init theme manager
        self::initThemeManager();

        // render script
        $content = self::renderView($controller->getViewScript(), get_object_vars($controller->view));
        
        // find layout script path
        $layoutName = null;
        $layoutPath = null;
        
        if(isset($controller->overrides['layout']))
        {
            $layoutName = $controller->overrides['layout'];
            $layoutPath = self::findLayout($layoutName);
        }
        
        if($layoutPath === null)
        {
            $layoutName = ThemeManager::$defaultLayout;
            $layoutPath = self::findLayout($layoutName);
            
            if($layoutPath === null) Toby::finalize('default layout "'.$layoutName.'" could not be found.');
        }
        
        // gather assets
        $assetSets = Assets::gatherSets($layoutName, Toby::getInstance()->resolve);
        
        // init layout
        $layout = new Layout($layoutPath, get_object_vars($controller->layout), $assetSets);
        
        // set layout title
        $title = isset($controller->overrides['layout_title']) ? (string)$controller->overrides['layout_title'] : (string)Config::get('toby.default.title');
        if(isset($controller->overrides['layout_title_app'])) $title = $title.$controller->overrides['layout_title_app'];
        if(isset($controller->overrides['layout_title_prep'])) $title = $controller->overrides['layout_title_prep'].$title;
        
        $layout->setTitle($title);
        
        // set layout js vars
        $layout->setJavaScriptVars(get_object_vars($controller->javascript));
        
        // set layout body attributes
        if(isset($controller->overrides['layout_body_id']))
        {
            $layout->setBodyId($controller->overrides['layout_body_id']);
        }

        if(isset($controller->overrides['layout_body_classes']))
        {
            $layout->addBodyClass($controller->overrides['layout_body_classes']);
        }
        
        // set layout content
        $layout->setContent($content);

        // render layout & return
        return $layout->render();
    }
    
    public static function renderView($scriptName, $vars = null)
    {
        // init theme manager
        self::initThemeManager();
        
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
    
    private static function initThemeManager()
    {
        // cancellation
        if(ThemeManager::isInitialized()) return;
        if(!ThemeManager::init()) Toby::finalize('unable to init theme manager');
    }
}
