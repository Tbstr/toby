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

// require autoloader
require_once 'Toby_Autoloader.class.php';

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

    /**
     * @var \Logger
     */
    private $requestTimesLogger;
    
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

        // require composer autoloader
        require_once COMPOSER_PATH . '/autoload.php';

        // normalize input
        if(is_string($request))     $request = trim($request, ' /');
        else                        $request = false;

        // set vars
        $this->request          = $request;
        $this->scope            = $scope;
        $this->startupTime      = time();
        
        // register autoloader
        spl_autoload_register(array('Toby_Autoloader', 'load'), true, true);
        
        // sessions
        if($scope === self::SCOPE_LOCAL) Toby_Session::$enabled = false;
        
        // init config & hook
        Toby_Config::readDir(APP_ROOT.'/config');
        $this->hook('configs_loaded');
        
        // error handling
        if(Toby_Config::get('toby')->getValue('strictErrors'))  error_reporting(E_ALL | E_STRICT);
        else                                                error_reporting(E_ALL & ~E_STRICT);
        
        ini_set('display_errors', Toby_Config::get('toby')->getValue('displayErrors') ? '1' : '0');
        
        // set url vars
        $this->appURL           = Toby_Config::get('toby')->hasKey('appURL') ? Toby_Config::get('toby')->getValue('appURL') : '';
        $this->appURLSecure     = Toby_Config::get('toby')->hasKey('secureAppURL') ? Toby_Config::get('toby')->getValue('secureAppURL') : $this->appURL;
        $this->appURLRelative   = preg_replace('/https?:\/\/[a-zA-Z0-9.-_]+\.[a-zA-Z]{2,4}(:[0-9]+)?\/?/', '/', $this->appURL);

        // init logging
        Toby_Logging::init();

        if(Toby_Config::get('toby')->getValue('logRequestTimes'))
        {
            $this->logRequestTime = true;
            $this->requestTimesLogger = \Logger::getLogger("toby.request-times");
            $this->requestTimesLogger->info("[APP START]");
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
        if(Toby_Config::get('toby')->hasKey('forceResolve')) $request = Toby_Config::get('toby')->getValue('forceResolve');
        
        // resolve and boot
        if($request !== false)
        {
            // resolve
            $elements = explode('/', $request);

            $controllerName = !empty($elements[0]) ? strtolower($elements[0]) : 'index';
            $actionName     = !empty($elements[1]) ? strtolower($elements[1]) : 'index';
            $arguments      = !empty($elements[2]) ? array_slice($elements, 2) : null;
            
            // boot
            $this->boot($controllerName, $actionName, $arguments, true);
        }
    }
    
    public function boot($controllerName, $actionName = 'index', $arguments = null, $stdResolveOnFail = false)
    {
        // set resolve
        $this->resolve = "$controllerName/$actionName".(empty($arguments) ? '' : '/'.implode('/', $arguments));

        // run action
        $controller = $this->runAction($controllerName, $actionName, $arguments);
        
        if($controller === false)
        {
            // default resolve on fail
            if($stdResolveOnFail)
            {
                if(Toby_Config::get('toby')->hasKey('defaultResolve'))
                {
                    // reboot
                    list($controllerName, $actionName) = explode('/', Toby_Config::get('toby')->getValue('defaultResolve'));
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
    
    public function runAction($controllerName, $actionName = 'index', $arguments = null)
    {
        // start timing;
        $this->requestTimeLogStart("$controllerName/$actionName".(empty($arguments) ? '' : '/'.implode('/', $arguments)));
        
        // vars
        $controllerFullName = 'Controller_'.strtoupper($controllerName[0]).substr($controllerName, 1);
        $actionFullName     = $actionName . 'Action';
        
        // exec
        if(file_exists(APP_ROOT."/controller/$controllerFullName.class.php"))
        {
            require_once APP_ROOT."/controller/$controllerFullName.class.php";
            
            if(class_exists($controllerFullName))
            {
                $controllerInstance = new $controllerFullName($controllerName, $actionName, $arguments);
                
                if(method_exists($controllerInstance, $actionFullName))
                {
                    // call
                    if(empty($arguments))   call_user_func(array($controllerInstance, $actionFullName));
                    else                    call_user_func_array (array($controllerInstance, $actionFullName), $arguments);
                    
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

                        // rebuild arguments
                        if(empty($arguments))       $arguments = array($actionName);
                        else                        array_unshift($arguments, $actionName);

                        // return
                        return $this->runAction($controllerName, 'default', $arguments);
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
        $this->requestTimesLogger->info("running action: $title");
    }
    
    private function requestTimeLogStop($success)
    {
        // cancellation
        if($this->logRequestTime !== true) return;
        
        // get time and title
        list($title, $startTime) = array_pop($this->requestLogData);
        
        // log
        $deltatime = number_format((microtime(true) - $startTime) * 1000, 2);
        $this->requestTimesLogger->info("action done: $title [{$deltatime}ms]".($success ? '' : ' [action not found]'));
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
            $this->requestTimesLogger->info("rendering controller: {$controller->serialize()} [{$deltatime}ms]");
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
        
        if($this->logRequestTime === true) $this->requestTimesLogger->info('[APP END]');
    }
}