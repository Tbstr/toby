<?php

class Toby_View
{
    private $scriptPath;
    private $vars;
    
    function __construct($scriptPath, $vars = null)
    {
        // set vars
        $this->scriptPath = $scriptPath;
        $this->vars = $vars;
        
        // apply view vars
        if($vars != null) foreach($vars as $key => $value) $this->$key = $value;
    }
    
    /* rendering */
    public function render($scriptOverride = null)
    {
        // var
        $scriptPath = $this->scriptPath;

        // script override
        if(is_string($scriptOverride))
        {
            $overrideScriptPath = Toby_Renderer::findViewScript($scriptOverride);
            if($overrideScriptPath !== false) $scriptPath = $overrideScriptPath;
        }
        
        // set
        $this->themeURL = Toby_ThemeManager::$themeURL;
        
        // render
        ob_start();
        include($scriptPath);
        $content = ob_get_contents();
        ob_end_clean();
        
        // return
        return $content;
    }
    
    /* partials */
    protected function partial($scriptName, $vars = null, $includeParentVars = false)
    {
        // find script
        $scriptPath = Toby_Renderer::findViewScript($scriptName);
        if($scriptPath === false) return 'Script "'.$scriptName.'" could not be found.';
        
        // manage vars
        $viewVars = null;
        if($includeParentVars && !empty($vars) && !empty($this->vars)) $viewVars = array_merge($this->vars, $vars);
        else $viewVars = &$vars;
        
        // create view and render
        $partialView = new Toby_View($scriptPath, $viewVars);
        return $partialView->render();
    }
    
    protected function partialLoop($scriptName, $elements, $includeParentVars = false)
    {
        $buffer = '';
        
        foreach($elements as $element)
        {
            $buffer .= $this->partial($scriptName, $element, $includeParentVars);
        }
        
        return $buffer;
    }
    
    protected function includeAction($controllerName, $actionName = 'index', $vars = null)
    {
        $controller = Toby::runAction($controllerName, $actionName, $vars);
        if($controller === false) Toby::finalize("includeAction: $controllerName/$actionName does not exist");
        
        return Toby_Renderer::renderView($controller->getViewScript(), get_object_vars($controller->view));
    }
}
