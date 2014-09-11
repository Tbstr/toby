<?php

abstract class Toby_Controller
{
    /* controller vars */
    public $name;
    public $action;
    public $attributes;
    
    public $toby;
    
     /* theme vars */
    public $themeOverride;
    public $themeConfigOverride;
    
    /* layout vars */
    public $layoutName              = 'default';
    
    public $layoutTitle             = '';
    public $layoutHeadContent       = '';
    
    public $layoutScripts           = array();
    public $layoutStyles            = array();
    
    public $layoutBodyId            = '';
    public $layoutBodyClasses       = array();
    
    /* view vars */
    public $renderView              = true;
    private $viewScriptOverride;
    
    /* data vars */
    public $layout;
    public $view;
    public $javascript;
    
    function __construct($name, $action, $attributes = null)
    {
        // vars
        $this->name                     = $name;
        $this->action                   = $action;
        $this->attributes               = $attributes;
        
        $this->toby                     = Toby::getInstance();
        
        if(Toby_Config::_hasKey('toby', 'defaultTitle')) $this->layoutTitle = Toby_Config::_getValue ('toby', 'defaultTitle', 'string');
        
        // holders
        $this->layout                   = new stdClass();
        $this->view                     = new stdClass();
        $this->javascript               = new stdClass();
        
        // default vars layout
        $this->layout->appURL           = $this->toby->appURL;
        $this->layout->url              = Toby_Utils::pathCombine(array($this->toby->appURL, $this->toby->request));
        
        // default vars view
        $this->view->appURL             = $this->toby->appURL;
        $this->view->url                = Toby_Utils::pathCombine(array($this->toby->appURL, $this->toby->request));
        
        // default vars javascript
        $this->javascript->xsrfkeyname  = Toby_Security::XSRFKeyName;
        $this->javascript->xsrfkey      = Toby_Security::XSRFGetKey();
    }
    
    protected function forward($controller, $action = 'index', $attributes = null, $externalForward = false, $forceSecure = false)
    {
        // convert attributes to array
        if(!empty($attributes) && !is_array($attributes)) $attributes = array((string)$attributes);
        
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
        header("Content-Type: ". ($mimeType == 'auto' ? Toby_Utils::getMIMEFromExtension($filePath) : $mimeType));
        header("Content-Disposition: attachment; filename=\"".($nameOverride == null ? basename($filePath) : $nameOverride)."\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: ".filesize($filePath));
        
        // read file & exit
        readfile($filePath);
        Toby::finalize(0);
    }
    
    /* set theme */
    protected function setTheme($themeName, $configName = false)
    {
        $this->themeOverride = $themeName;
        if($configName) $this->themeConfigOverride = $configName;
    }
    
    /* set layout */
    protected function setLayout($layoutName)
    {
        $this->layoutName = $layoutName;
    }
    
    protected function setTitle($value)
    {
        $this->layoutTitle = $value;
    }
    
    protected function appendToTitle($value)
    {
        $this->layoutTitle = $this->layoutTitle.$value;
    }
    
    protected function prependToTitle($value)
    {
        $this->layoutTitle = $value.$this->layoutTitle;
    }
    
    /* set head information */
    protected function setHeadContent($content, $append = false)
    {
        if($append === true) $this->layoutHeadContent .= $content;
        else $this->layoutHeadContent = $content;
    }
    
    protected function setBodyId($id)
    {
        $this->layoutBodyId = $id;
    }
    
    protected function addBodyClass()
    {
        for($i = 0, $num = func_num_args(); $i < $num; $i++)
        {
            $arg = func_get_arg($i);
            if(!is_string($arg)) continue;
            
            $this->layoutBodyClasses = array_merge($this->layoutBodyClasses, explode(' ', $arg));
        }
    }
    
    protected function removeBodyClass()
    {
        for($i = 0, $num = func_num_args(); $i < $num; $i++)
        {
            $key = array_search(func_get_arg($i), $this->layoutBodyClasses);
            if($key !== false) array_splice($this->layoutBodyClasses, $key, 1);
        }
    }
    
    protected function addScript($scriptPath)
    {
        // add
        $this->layoutScripts[] = $scriptPath;
    }
    
    protected function addStyle($stylePath, $media = 'all')
    {
        // init media entry
        if(!isset($this->layoutStyles[$media])) $this->layoutStyles[$media] = array();
        
        // add
        $this->layoutStyles[$media][] = $stylePath;
    }
    
    /* get set view script */
    protected function setViewScript($viewScript)
    {
        $this->viewScriptOverride = $viewScript;
    }
    
    public function getViewScript()
    {
        if(empty($this->viewScriptOverride)) return "$this->name/$this->action";
        else return $this->viewScriptOverride;
    }
    
    /* to string */
    public function __toString()
    {
        return "Toby_Controller[{$this->serialize()}]";
    }
    
    public function serialize()
    {
        return "$this->name/$this->action";
    }
}