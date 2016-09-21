<?php

namespace Toby;

use \InvalidArgumentException;
use Logger;
use stdClass;
use Toby\Logging\Logging;
use Toby\Utils\Utils;

abstract class Controller
{
    /* variables */
    public $name;
    public $action;
    public $attributes;
    
    /* settings */
    public $renderView              = true;
    
    /* overrides */
    public $overrides               = [];
    
    /* data container */
    public $layout;
    public $view;
    public $javascript;

    /** @var Logger  */
    protected $logger;

    /** @var Toby */
    protected $toby;

    /* static vars */
    private static $helpers         = [];
    
    function __construct($name, $action, $attributes = null)
    {
        // vars
        $this->name                     = $name;
        $this->action                   = $action;
        $this->attributes               = $attributes;

        $this->logger                   = Logging::logger($this);
        $this->toby                     = Toby::getInstance();
        
        // holders
        $this->layout                   = new stdClass();
        $this->view                     = new stdClass();
        $this->javascript               = new stdClass();
        
        // default vars layout
        $this->layout->appURL           = $this->toby->appURL;
        $this->layout->url              = Utils::pathCombine(array($this->toby->appURL, $this->toby->request));
        
        // default vars view
        $this->view->appURL             = $this->toby->appURL;
        $this->view->url                = Utils::pathCombine(array($this->toby->appURL, $this->toby->request));
        
        // default vars javascript
        $this->javascript->xsrfkeyname  = Security::XSRFKeyName;
        $this->javascript->xsrfkey      = Security::XSRFGetKey();
    }
    
    protected function forward($controller, $action = 'index', $attributes = null, $externalForward = false, $forceSecure = false)
    {
        // convert attributes to array
        if(!empty($attributes) && !is_array($attributes)) $attributes = array($attributes);
        
        // forward
        if($externalForward) header('Location: '.($forceSecure ? $this->toby->appURLSecure : $this->toby->appURL).DS.$controller.DS.$action.($attributes ? DS.implode(DS, $attributes) : ''));
        else Toby::getInstance()->boot($controller, $action, $attributes);
        
        // exit
        Toby::finalize(0);
    }
    
    protected function forwardToURL($url)
    {
        header('Location: '.$url);
        Toby::finalize(0);
    }
    
    protected function returnFile($filePath, $nameOverride = null,  $mimeType = 'auto')
    {
        // set header information
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Description: File Transfer");

        if($mimeType === 'auto') $mimeType = Utils::getMIMEFromExtension(empty($nameOverride) ? $filePath : $nameOverride);
        if(!empty($mimeType)) header("Content-Type:$mimeType");

        header("Content-Disposition: attachment; filename=\"".(empty($nameOverride) ? basename($filePath) : $nameOverride)."\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: ".filesize($filePath));
        
        // read file
        readfile($filePath);
    }
    
    /* set theme */
    protected function setTheme($themeName, $functionName = null)
    {
        $this->overrides['theme'] = $themeName;
        
        if(!empty($functionName))
        {
            $this->overrides['theme_function'] = $functionName;
        }
    }
    
    /* set layout */
    protected function setLayout($layoutName)
    {
        $this->overrides['layout'] = $layoutName;
    }
    
    /* page title */
    protected function setTitle($value)
    {
        $this->overrides['layout_title'] = $value;
    }
    
    protected function appendToTitle($value)
    {
        if(!isset($this->overrides['layout_title_app'])) $this->overrides['layout_title_app'] = '';
        $this->overrides['layout_title_app'] .= $value;
    }
    
    protected function prependToTitle($value)
    {
        if(!isset($this->overrides['layout_title_prep'])) $this->overrides['layout_title_prep'] = '';
        $this->overrides['layout_title_prep'] = $value.$this->overrides['layout_title_prep'];
    }
    
    /* body properties */
    protected function setBodyId($id)
    {
        $this->overrides['layout_body_id'] = $id;
    }
    
    protected function addBodyClass()
    {
        if(!isset($this->overrides['layout_body_classes'])) $this->overrides['layout_body_classes'] = [];
        
        for($i = 0, $num = func_num_args(); $i < $num; $i++)
        {
            $arg = func_get_arg($i);
            if(!is_string($arg)) continue;

            $this->overrides['layout_body_classes'] = array_merge($this->overrides['layout_body_classes'], explode(' ', $arg));
        }
    }
    
    protected function removeBodyClass()
    {
        if(!isset($this->overrides['layout_body_classes'])) return;
        
        for($i = 0, $num = func_num_args(); $i < $num; $i++)
        {
            $key = array_search(func_get_arg($i), $this->overrides['layout_body_classes']);
            if($key !== false) array_splice($this->overrides['layout_body_classes'], $key, 1);
        }
    }
    
    /* manage view rendering */
    protected function setViewScript($viewScript)
    {
        $this->overrides['view_script'] = $viewScript;
    }
    
    public function getViewScript()
    {
        if(isset($this->overrides['view_script'])) return $this->overrides['view_script'];
        return "$this->name/$this->action";
    }
    
    protected function disableRendering()
    {
        $this->renderView = false;
    }

    /* helper management */
    public static function registerHelper($functionName, $callable)
    {
        // cancellation
        if(!is_string($functionName))   throw new InvalidArgumentException('argument functionName is not of type string');
        if(!is_callable($callable))     throw new InvalidArgumentException('argument callable is not of type $callable');

        // check for existence
        if(isset(self::$helpers[$functionName])) throw new \Exception('Helper "'.$functionName.'" is already set');

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
        return "Controller[{$this->name}/{$this->action}]";
    }
}
