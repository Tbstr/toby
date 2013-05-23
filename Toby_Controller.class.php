<?php

abstract class Toby_Controller
{
    public $name;
    public $action;
    
    public $layoutName              = 'default';
    public $layoutTitle             = '';
    public $layoutBodyId            = '';
    public $layoutBodyClasses       = array();
    
    public $renderView              = true;
    
    private $viewScriptOverride;
    
    public $themeOverride;
    public $themeConfigOverride;
    
    public $layout;
    public $view;
    public $javascript;
    
    function __construct($name, $action)
    {
        // vars
        $this->name         = $name;
        $this->action       = $action;
        
        if(Toby_Config::_hasKey('toby', 'defaultTitle')) $this->layoutTitle = Toby_Config::_getValue ('toby', 'defaultTitle', 'string');
        
        // holders
        $this->layout       = new stdClass();
        $this->view         = new stdClass();
        $this->javascript   = new stdClass();
        
        // view default vars
        $this->view->appURL = APP_URL;
        $this->view->url    = APP_URL.DS.REQUEST;
    }
    
    protected function forward($controller, $action = 'index', $vars = null, $externalForward = false, $forceSecure = false)
    {
        // convert attributes to array
        if(!empty($vars) && !is_array($vars)) $vars = array((string)$vars);
        
        // forward
        if($externalForward) header('Location: '.($forceSecure ? SECURE_APP_URL : APP_URL).DS.$controller.DS.$action.($vars ? DS.implode(DS, $vars) : ''));
        else Toby::boot($controller, $action, $vars);
        
        // exit
        exit(0);
    }
    
    protected function forwardToURL($url)
    {
        header('Location: '.$url);
        exit(0);
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
        exit(0);
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
    
    /* set title */
    protected function setTitle($value)
    {
        $this->layoutTitle = $value;
    }
    
    protected function appendTitle($value)
    {
        $this->layoutTitle = $this->layoutTitle.$value;
    }
    
    protected function prependTitle($value)
    {
        $this->layoutTitle = $value.$this->layoutTitle;
    }
    
    /* set body attributes */
    protected function setBodyId($id)
    {
        $this->layoutBodyId = $id;
    }
    
    protected function addBodyClass()
    {
        for($i = 0; $i < func_num_args(); $i++)
        {
            $arg = func_get_arg($i);
            if(!is_string($arg)) continue;
            
            $this->layoutBodyClasses = array_merge($this->layoutBodyClasses, explode(' ', $arg));
        }
    }
    
    protected function removeBodyClass()
    {
        for($i = 0; $i < func_num_args(); $i++)
        {
            $key = array_search(func_get_arg($i), $this->layoutBodyClasses);
            if($key !== false) array_splice($this->layoutBodyClasses, $key, 1);
        }
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
    public function toString()
    {
        return "[Toby_Controller {$this->serialize()}]";
    }
    
    public function serialize()
    {
        return "$this->name/$this->action";
    }
}