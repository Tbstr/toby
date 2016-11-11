<?php

namespace Toby;

use Toby\Exceptions\TobyException;
use Toby\HTTP\Response;
use Toby\Logging\Logging;

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

// init auto loaders
if(defined('COMPOSER_ROOT'))
{
    $composerAutoloaderPath = COMPOSER_ROOT.'/autoload.php';
    if(is_file($composerAutoloaderPath)) require_once $composerAutoloaderPath;
}

require_once 'Autoloader.php';

Autoloader::addNamespaces(
    array(
        'Toby'          => TOBY_ROOT,
        'Controller'    => APP_ROOT.'/controller',
        'Model'         => APP_ROOT.'/model'
    )
);

spl_autoload_register(array('\Toby\Autoloader', 'load'), true, true);

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
    
    /** @var Response  */
    public $response                    = null;
    
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
        else throw new TobyException('Toby is a singleton and therefore can only be accessed through Toby::getInstance().');
    }
    
    /* static getter*/

    /** @return Toby */
    public static function getInstance()
    {
        if(self::$instance === null) new self();
        return self::$instance;
    }
            
    /* object methods */

    /**
     * @param string|null $request
     * @param string $scope
     */
    public function init($request = null, $scope)
    {
        // include pre init hook
        $this->hook('pre_init');

        // normalize input
        $request = is_string($request) ? trim($request, ' /') : null;

        // set vars
        $this->request          = $request;
        $this->scope            = $scope;
        $this->startupTime      = time();

        // sessions
        if($scope === self::SCOPE_LOCAL) Session::$enabled = false;

        // init config & hook
        Config::getInstance()->readDir(APP_ROOT.'/config');
        $this->hook('configs_loaded');
        
        // error handling
        if(Config::get('toby.error.strict')) error_reporting(E_ALL | E_STRICT);
        else error_reporting(E_ALL & ~E_STRICT);
        
        ini_set('display_errors', Config::get('toby.error.display') ? '1' : '0');
        
        // set url vars
        $this->appURL           = Config::has('toby.app.url') ? Config::get('toby.app.url') : '';
        $this->appURLSecure     = Config::has('toby.app.url_secure') ? Config::get('toby.app.url_secure') : $this->appURL;
        $this->appURLRelative   = preg_replace('/https?:\/\/[a-zA-Z0-9.-_]+\.[a-zA-Z]{2,4}(:[0-9]+)?\/?/', '/', $this->appURL);

        // init logging
        Logging::init();

        if(Config::get('toby.logging.log_request_times'))
        {
            $this->logRequestTime = true;
            $this->requestTimesLogger = \Logger::getLogger("toby.request-times");
            $this->requestTimesLogger->info("[APP START]");
        }
        
        // set encoding
        if(empty($this->encoding)) $this->setEncoding('UTF-8');
        
        // init security
        Security::init();

        // set initialized
        $this->initialized = true;

        // include post init hook
        $this->hook('post_init');

        // request manipulation: force resolve
        if(Config::has('toby.request.force_resolve'))
        {
            $request = Config::get('toby.request.force_resolve');
        }
        
        // request manipulation: aliases
        else if(Config::has('toby.request.aliases'))
        {
            $requestAliases = Config::get('toby.request.aliases');
            if(isset($requestAliases[$request])) $request = $requestAliases[$request];
        }
        
        // resolve and boot
        if($request !== null)
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
        $response = $this->runAction($controllerName, $actionName, $arguments);

        if($response !== null)
        {
            // send response
            $response->send();
        }
        else
        {
            // default resolve on fail
            if($stdResolveOnFail)
            {
                if(Config::has('toby.request.default_resolve'))
                {
                    // reboot
                    list($controllerName, $actionName) = explode('/', Config::get('toby.request.default_resolve'));
                    $this->boot($controllerName, $actionName);
                }
            }
        }
    }

    /**
     * @param string $controllerName
     * @param string $actionName
     * @param null   $arguments
     *
     * @return Response|null
     */
    public function runAction($controllerName, $actionName = 'index', $arguments = null)
    {
        // start timing;
        $this->requestTimeLogStart("$controllerName/$actionName".(empty($arguments) ? '' : '/'.implode('/', $arguments)));

        // load controller class
        $controller = Autoloader::getControllerInstance($controllerName);
        
        if($controller !== null)
        {
            $actionResponse = $controller->callAction($actionName, $arguments);
            
            // success return
            if($actionResponse !== null)
            {
                // stop timing
                $this->requestTimeLogStop(true);

                // return response
                return $actionResponse;
            }
            
            // fail try default action
            else
            {
                // stop timing
                $this->requestTimeLogStop(false);
                
                // call default action
                if($actionName !== 'default')
                {
                    // rebuild arguments
                    if(empty($arguments))       $arguments = array($actionName);
                    else                        array_unshift($arguments, $actionName);
                    
                    // restart timing;
                    $this->requestTimeLogStart("$controllerName/default".(empty($arguments) ? '' : '/'.implode('/', $arguments)));

                    // call action
                    $defaultResponse = $controller->callAction('default', $arguments);
                    
                    // stop timing
                    $this->requestTimeLogStop($defaultResponse !== null);

                    // return
                    return $defaultResponse;
                }
            }
        }
        
        // stop timing
        $this->requestTimeLogStop(false);
        
        // return
        return null;
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
        $hookPath = APP_ROOT.'/hooks/'.$name.'.php';
        if(is_file($hookPath)) include $hookPath;
    }
    
    /* finalization */
    public function finalize($exitCodeOrText = 0)
    {
        // exit
        if(is_int($exitCodeOrText))
        {
            exit($exitCodeOrText);
        }
        else
        {
            echo $exitCodeOrText;
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
