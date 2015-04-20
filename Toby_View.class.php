<?php

class Toby_View
{
    /* variables */
    private $scriptPath;
    private $vars;
    
    private $toby;

    /* static variables */
    private static $helpers     = array();

    /* constructor */
    function __construct($scriptPath, $vars = null)
    {
        // apply view vars
        if($vars !== null) foreach($vars as $key => $value) $this->$key = $value;
        
        // set vars
        $this->scriptPath   = $scriptPath;
        $this->vars         = $vars;
        $this->toby         = Toby::getInstance();
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
        $controller = Toby::getInstance()->runAction($controllerName, $actionName, $vars);
        if($controller === false) Toby::finalize("includeAction: $controllerName/$actionName does not exist");
        
        return Toby_Renderer::renderView($controller->getViewScript(), get_object_vars($controller->view));
    }
    
    protected function includeFile($pathToFile, $prependThemePath = true)
    {
        // cancellation
        if(empty($pathToFile)) return false;
        
        // prepend theme path
        if($prependThemePath) $pathToFile = Toby_Utils::pathCombine (array(Toby_ThemeManager::$themePathRoot, $pathToFile));
        
        // return content
        return file_get_contents($pathToFile);
    }
    
    /* helper management */
    public static function registerHelper($functionName, $callable)
    {
        // cancellation
        if(!is_string($functionName))   throw new InvalidArgumentException('argument functionName is not of type string');
        if(!is_callable($callable))     throw new InvalidArgumentException('argument callable is not of type $callable');

        // check for existence
        if(isset(self::$helpers[$functionName])) throw new Exception('Helper "'.$functionName.'" is already set');

        // register
        self::$helpers[$functionName] = $callable;
    }

    public function __call($name, $arguments)
    {
        // cancellation
        if(!isset(self::$helpers[$name])) return;

        // call
        return call_user_func_array(self::$helpers[$name], $arguments);
    }
    
    /* to string */
    public function __toString()
    {
        return "Toby_View[$this->scriptPath]";
    }
}
