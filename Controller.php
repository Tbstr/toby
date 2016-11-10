<?php

namespace Toby;

use Exception;
use Logger;
use stdClass;
use Toby\Exceptions\TobyException;
use Toby\HTTP\JSONResponse;
use Toby\HTTP\RedirectResponse;
use Toby\HTTP\Response;
use Toby\HTTP\StreamedResponse;
use Toby\Logging\Logging;
use Toby\Utils\StringUtils;
use Toby\Utils\TypeUtils;

abstract class Controller
{
    /* properties */
    public $name;
    public $action;
    public $attributes;
    
    /* settings */
    private $renderView     = true;
    
    /* overrides */
    public $overrides       = [];
    
    /* data container */
    public $layout;
    public $view;
    public $javascript;

    /** @var Logger  */
    protected $logger;

    /** @var Toby */
    protected $toby;

    /** @var Response|JSONResponse|StreamedResponse|RedirectResponse */
    public $response;

    /* static vars */
    private static $helpers = [];
    
    /* constructor */
    function __construct($name)
    {
        // vars
        $this->name                     = $name;

        $this->toby                     = Toby::getInstance();
        $this->logger                   = Logging::logger($this);

        $this->response                 = new Response();
        
        // init data containers
        $this->layout                   = new stdClass();
        $this->layout->appURL           = $this->toby->appURL;
        $this->layout->url              = StringUtils::buildPath(array($this->toby->appURL, $this->toby->request));
        
        $this->view                     = new stdClass();
        $this->view->appURL             = $this->toby->appURL;
        $this->view->url                = StringUtils::buildPath(array($this->toby->appURL, $this->toby->request));
        
        $this->javascript               = new stdClass();
        $this->javascript->xsrfkeyname  = Security::XSRFKeyName;
        $this->javascript->xsrfkey      = Security::XSRFGetKey();
    }
    
    /* action management */

    /**
     * @param string     $actionName
     * @param array|null $attributes
     *
     * @return mixed
     * @throws TobyException
     */
    public function callAction($actionName, array $attributes = null, $renderView = true)
    {
        $actionMethodName = $actionName.'Action';
        if(method_exists($this, $actionMethodName))
        {
            // call
            if($attributes === null)
            {
                $response = call_user_func(array($this, $actionMethodName));
            }
            else
            {
                $response = call_user_func_array(array($this, $actionMethodName), $attributes);
            }
            
            // render default response if none given
            if($response === null)
            {
                // get default response from controller
                $response = $this->response;
                
                // set response content with rendered action view
                if($renderView && $this->renderingEnabled() && get_class($response) === Response::class)
                {
                    $renderedContent = $this->renderActionView($actionName);
                    
                    if($renderedContent !== null)
                    {
                        $response->setContent($renderedContent);
                    }
                }
            }
            
            // return
            return $response;
        }
        
        return null;
    }
    
    protected function forward($controller, $action = 'index', $attributes = null, $externalForward = false, $forceSecure = false)
    {
        // convert attributes to array
        if(!empty($attributes) && !is_array($attributes)) $attributes = array($attributes);
        
        // forward
        if($externalForward)
        {
            $url = ($forceSecure ? $this->toby->appURLSecure : $this->toby->appURL).DS.$controller.DS.$action.($attributes ? DS.implode(DS, $attributes) : '');
            
            $response = new RedirectResponse($url);
            $response->send();
        }
        else
        {
            Toby::getInstance()->boot($controller, $action, $attributes);
        }
        
        // exit
        $this->toby->finalize(0);
    }
    
    protected function forwardToURL($url)
    {
        $response = new RedirectResponse($url);
        $response->send();
        
        $this->toby->finalize(0);
    }
    
    protected function returnFile($filePath, $nameOverride = null, $mimeType = 'auto')
    {
        // set header information
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Description: File Transfer");

        if($mimeType === 'auto') $mimeType = TypeUtils::getMIMEFromExtension(empty($nameOverride) ? $filePath : $nameOverride);
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
    
    /* manage page title */
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
    
    /* response management */
    protected function setResponse(Response $response)
    {
        // cancellation
        if($response === null) return;
        
        // set
        $this->response = $response;
    }
    
    /* manage view rendering */
    protected function setViewScript($viewScript)
    {
        $this->overrides['view_script'] = $viewScript;
    }
    
    protected function disableRendering()
    {
        $this->renderView = false;
    }
    
    public function renderingEnabled()
    {
        return $this->renderView;
    }

    protected function renderActionView($actionName)
    {
        
        // cancellation
        if(!$this->renderingEnabled()) return null;

        // start timing
        /*
        $starttime = null;
        if($this->logRequestTime) $starttime = microtime(true);
        */

        // init theme manager
        $theme          = null;
        $themeFunction  = null;

        if(isset($this->overrides['theme']))
        {
            $theme          = $this->overrides['theme'];
            $themeFunction  = isset($this->overrides['theme_function']) ? $this->overrides['theme_function'] : null;
        }
        
        if(!ThemeManager::init($theme, $themeFunction)) $this->toby->finalize('unable to init ThemeManager with controller '.$this);

        // determine view script
        if(isset($this->overrides['view_script']))
        {
            $viewScript = $this->overrides['view_script'];
        }
        else
        {
            $viewScript = "$this->name/$actionName";
        }
        
        // render content
        $content = Renderer::renderPage($this, $viewScript);

        // stop timing
        /*
        if($this->logRequestTime)
        {
            $deltatime = number_format((microtime(true) - $starttime) * 1000, 2);
            $this->requestTimesLogger->info("rendering controller: $controller [{$deltatime}ms]");
        }
        */

        // return
        return $content;
    }
    
    /* helper management */

    /**
     * Registers helper function to be called via this class. Executed with __call magic method.
     * 
     * @param string $functionName
     * @param callable $callable
     *
     * @throws TobyException
     */
    public static function registerHelper($functionName, callable $callable)
    {
        // check for existence
        if(isset(self::$helpers[$functionName])) throw new TobyException('Helper "'.$functionName.'" is already set');

        // register
        self::$helpers[$functionName] = $callable;
    }

    public function __call($name, $arguments)
    {
        // cancellation
        if(!isset(self::$helpers[$name])) throw new Exception("call to undefined function $name");

        // call
        return call_user_func_array(self::$helpers[$name], $arguments);
    }

    /* to string */
    public function __toString()
    {
        return "Controller[{$this->name}]";
    }
}
