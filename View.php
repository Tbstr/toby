<?php

namespace Toby;

use \InvalidArgumentException;
use Toby\Exceptions\TobyException;
use Toby\Utils\Utils;

class View
{
    /* variables */
    private $scriptPath;
    private $vars;

    private $themeURL;

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
        // get script path
        if(is_string($scriptOverride))
        {
            $scriptPath = Renderer::findViewScript($scriptOverride);
            if($scriptPath === null) throw new TobyException("override script $scriptOverride does not exist");
        }
        else
        {
            $scriptPath = $this->scriptPath;
        }
        
        // set
        $this->themeURL = ThemeManager::$themeURL;

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
        $scriptPath = Renderer::findViewScript($scriptName);
        if($scriptPath === null) throw new TobyException("script $scriptName does not exist");
        
        // manage vars
        $viewVars = null;
        if($includeParentVars && !empty($vars) && !empty($this->vars)) $viewVars = array_merge($this->vars, $vars);
        else $viewVars = &$vars;
        
        // create view and render
        $partialView = new View($scriptPath, $viewVars);
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
        if($controller !== null)
        {
            return Renderer::renderView($controller->getViewScript(), get_object_vars($controller->view));
        }
        else
        {
            Toby::finalize("includeAction: $controllerName/$actionName does not exist");
            return null;
        }
    }
    
    protected function includeFile($pathToFile, $prependThemePath = true)
    {
        // cancellation
        if(!is_string($pathToFile) || empty($pathToFile)) throw new InvalidArgumentException('argument $pathToFile is not of type string or empty');
        
        // prepend theme path
        if($prependThemePath) $pathToFile = Utils::pathCombine (array(ThemeManager::$themePathRoot, $pathToFile));
        
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
        if(isset(self::$helpers[$functionName])) throw new TobyException('Helper "'.$functionName.'" is already set');

        // register
        self::$helpers[$functionName] = $callable;
    }

    public function __call($name, $arguments)
    {
        // cancellation
        if(!isset(self::$helpers[$name])) throw new \Exception("call to undefined function $name");

        // call
        return call_user_func_array(self::$helpers[$name], $arguments);
    }
    
    /* to string */
    public function __toString()
    {
        return "Toby_View[$this->scriptPath]";
    }
}
