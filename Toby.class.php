<?php

// check env vars
switch(false)
{
    case defined('PROJECT_ROOT'):
    case defined('APP_ROOT'):
    case defined('PUBLIC_ROOT'):
    case defined('TOBY_ROOT'):
        echo 'necessary environment variables not set';
        exit(1);
        break;
}

// change directory
chdir(PROJECT_ROOT);

// define constants
define('DS', DIRECTORY_SEPARATOR);
define('NL', "\n");

// add lib to include path
set_include_path(get_include_path().PATH_SEPARATOR.APP_ROOT.'/lib');

// core class
class Toby
{
    private static $instance            = null;
    
    /* variables */
    public $scope                       = false;
    
    public $appURL                      = false;
    public $appURLSecure                = false;
    public $appURLRelative              = false;
    
    public $request                     = false;
    public $resolve                     = false;
    
    public $startupTime                 = 0;
    public $encoding                    = false;

    private $logRequestTime             = false;
    private $requestLogData             = array();
    
    private $initialized                = false;
    
    /* constants */
    const SCOPE_WEB                     = 'web';
    const SCOPE_LOCAL                   = 'local';
    
    /* constructor */
    function __construct()
    {
        if(self::$instance === null) self::$instance  = $this;
        else throw new Exception('Toby is a singleton and therefore can only be accessed through Toby::getInstance().');
    }
    
    /* static getter*/
    public static function getInstance()
    {
        if(self::$instance === null) new self();
        return self::$instance;
    }
            
    /* object methods */
    public function init($request = false, $scope = false)
    {
        // include pre init hook
        $this->hook('pre_init');
        
        // set vars
        $this->request          = $request;
        $this->scope            = $scope;
        $this->startupTime      = time();
        
        // register autoloader
        spl_autoload_register(array($this, 'autoload'));
        
        // sessions
        if($scope === self::SCOPE_LOCAL) Toby_Session::$enabled = false;
        
        // init config & hook
        Toby_Config::getInstance()->readDir(APP_ROOT.'/config');
        $this->hook('configs_loaded');
        
        // error handling
        if(Toby_Config::_getValue('toby', 'strictErrors'))  error_reporting(E_ALL | E_STRICT);
        else                                                error_reporting(E_ALL & ~E_STRICT);
        
        ini_set('display_errors', Toby_Config::_getValue('toby', 'displayErrors') ? '1' : '0');
        
        // set url vars
        $this->appURL           = Toby_Config::_hasKey('toby', 'appURL') ? Toby_Config::_getValue('toby', 'appURL') : '';
        $this->appURLSecure     = Toby_Config::_hasKey('toby', 'secureAppURL') ? Toby_Config::_getValue('toby', 'secureAppURL') : $this->appURL;
        $this->appURLRelative   = preg_replace('/https?:\/\/[a-zA-Z0-9.-_]+\.[a-zA-Z]{2,4}\/?/', '/', $this->appURL);
        
        // init logging
        Toby_Logger::init(APP_ROOT.'/logs');
        Toby_Logger::logErrors('error');
        
        if(Toby_Config::_getValue('toby', 'logRequestTimes'))
        {
            $this->logRequestTime = true;
            Toby_Logger::log('[APP START]', 'request-times', true);
        }
        
        // set encoding
        if(empty($this->encoding)) $this->setEncoding('UTF-8');
        
        // init security
        Toby_Security::init();
        
        // set initialized
        $this->initialized = true;
        
        // include post init hook
        $this->hook('post_init');
        
        // force resolve
        if(Toby_Config::_hasKey('toby', 'forceResolve')) $request = Toby_Config::_getValue('toby', 'forceResolve');
        
        // resolve and boot
        if($request !== false)
        {
            // resolve
            $elements = explode('/', $request);

            $controllerName = !empty($elements[0]) ? strtolower($elements[0]) : 'index';
            $actionName     = !empty($elements[1]) ? strtolower($elements[1]) : 'index';
            $attributes     = (count($elements) > 2) ? array_slice($elements, 2) : null;
            
            // boot
            $this->boot($controllerName, $actionName, $attributes, true);
        }
    }
    
    public function boot($controllerName, $actionName = 'index', $attributes = null, $stdResolveOnFail = false)
    {
        // set resolve
        $this->resolve = "$controllerName/$actionName".($attributes === null ? '' : '/'.implode('/', $attributes));

        // run action
        $controller = $this->runAction($controllerName, $actionName, $attributes);
        
        if($controller === false)
        {
            // default resolve on fail
            if($stdResolveOnFail)
            {
                if(Toby_Config::_hasKey('toby', 'defaultResolve'))
                {
                    // reboot
                    list($controllerName, $actionName) = explode('/', Toby_Config::_getValue('toby', 'defaultResolve'));
                    $this->boot($controllerName, $actionName);
                }
            }
        }
        else
        {
            // include resolved hook
            $this->hook('resolved');
            
            // render
            echo $this->renderController($controller);
        }
    }
    
    public function runAction($controllerName, $actionName = 'index', $attributes = null)
    {
        // start timing;
        $this->requestTimeLogStart("$controllerName/$actionName".($attributes === null ? '' : '/'.implode('/', $attributes)));
        
        // vars
        $controllerFullName = 'Controller_'.strtoupper($controllerName[0]).substr($controllerName, 1);
        $actionFullName     = $actionName . 'Action';
        
        // exec
        if(file_exists(APP_ROOT."/controller/$controllerFullName.class.php"))
        {
            require_once APP_ROOT."/controller/$controllerFullName.class.php";
            
            if(class_exists($controllerFullName))
            {
                $controllerInstance = new $controllerFullName($controllerName, $actionName, $attributes);
                
                if(method_exists($controllerInstance, $actionFullName))
                {
                    // call
                    if($attributes === null) call_user_func(array($controllerInstance, $actionFullName));
                    else call_user_func_array (array($controllerInstance, $actionFullName), $attributes);
                    
                    // stop timing
                    $this->requestTimeLogStop(true);
                    
                    // return
                    return $controllerInstance;
                }
                else
                {
                    if($actionName !== 'default')
                    {
                        // stop timing
                        $this->requestTimeLogStop(false);

                        // return
                        return $this->runAction($controllerName, 'default', $attributes);
                    }
                }
            }
        }
        
        // stop timing
        $this->requestTimeLogStop(false);
        
        // return
        return false;
    }
    
    private function requestTimeLogStart($title)
    {
        // cancellation
        if($this->logRequestTime !== true) return;
        
        // store time and title
        $this->requestLogData[]  = array($title, microtime(true));
        
        // log
        Toby_Logger::log("running action: $title", 'request-times', true);
    }
    
    private function requestTimeLogStop($success)
    {
        // cancellation
        if($this->logRequestTime !== true) return;
        
        // get time and title
        list($title, $startTime) = array_pop($this->requestLogData);
        
        // log
        $deltatime = number_format((microtime(true) - $startTime) * 1000, 2);
        Toby_Logger::log("action done: $title [{$deltatime}ms]".($success ? '' : ' [action not found]'), 'request-times', true);
    }
    
    public function renderController(Toby_Controller $controller)
    {
        // cancellation
        if(!$controller->renderView) return '';
        
        // start timing
        if($this->logRequestTime) $starttime = microtime(true);
        
        // prepare theme manager
        if(!Toby_ThemeManager::initByController($controller)) $this->finalize('unable to set theme '.Toby_ThemeManager::$themeName);
        
        // render content
        $content = Toby_Renderer::renderPage($controller);
        
        // stop timing
        if($this->logRequestTime)
        {
            $deltatime = number_format((microtime(true) - $starttime) * 1000, 2);
            Toby_Logger::log("rendering controller: {$controller->serialize()} [{$deltatime}ms]", 'request-times', true);
        }
        
        // return
        return $content;
    }
    
    /* settings */
    public function setEncoding($encoding)
    {
        // set
        $this->encoding = $encoding;
        
        // set mb
        mb_internal_encoding($encoding);
    }
    
    /* autoloader */
    public function autoload($className)
    {
        // prepare
        $elements = explode('_', $className);
        
        if(count($elements) === 1) return;
        $basename = array_pop($elements);
        
        // resolve toby related
        if(strtolower($elements[0]) === 'toby')
        {
            $elements[0]    = TOBY_ROOT;
            $path = strtolower(implode('/', $elements))."/$className.class.php";

            if(is_file($path)) require_once($path);
            else
            {
                $path = strtolower(implode('/', $elements)).'/'.strtolower($basename)."/$className.class.php";
                if(is_file($path)) require_once($path);
            }
        }
        
        // resolve app related
        else
        {
            array_unshift($elements, APP_ROOT);
            $path = strtolower(implode('/', $elements))."/$className.class.php";

            if(is_file($path)) require_once($path);
            else
            {
                $path = strtolower(implode('/', $elements)).'/'.strtolower($basename)."/$className.class.php";
                if(is_file($path)) require_once($path);
            }
        }
    }
    
    /* helper */
    private function hook($name)
    {
        $hookPath = APP_ROOT.'/hooks/'.$name.'.hook.php';
        if(file_exists($hookPath)) include $hookPath;
    }
    
    /* finalization */
    public static function finalize($status = 0)
    {
        // exit
        if(is_int($status)) exit($status);
        else
        {
            echo Toby_Utils::printr($status);
            exit(1);
        }
    }
    
    function __destruct()
    {
        // complete time logging
        $count = count($this->requestLogData);
        while($count > 0)
        {
            $this->requestTimeLogStop(true);
            $count--;
        }
        
        if($this->logRequestTime === true) Toby_Logger::log('[APP END]', 'request-times', true);
    }
}