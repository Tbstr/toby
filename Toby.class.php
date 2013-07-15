<?php

// core class
class Toby
{
    /* variables */
    private static $logRequestTime      = false;
    private static $requestLogData      = array();
    
    public static $ENCODING             = null;
    
    /* constants */
    const SCOPE_WEB                     = 'web';
    const SCOPE_LOCAL                   = 'local';
    
    public static function init($request = null, $scope = 'local')
    {
        // check env vars
        switch(false)
        {
            case defined('PROJECT_ROOT'):
            case defined('APP_ROOT'):
            case defined('PUBLIC_ROOT'):
            case defined('TOBY_ROOT'):
                Toby::finalize('necessary environment variables not set');
                break;
        }
        
        // change directory
        chdir(PROJECT_ROOT);

        // define constants
        define('DS', DIRECTORY_SEPARATOR);
        define('NL', "\n");
        
        define('SCOPE', $scope);
        
        // register autoloader
        spl_autoload_register('Toby::autoload');
        
        // add lib to include path
        set_include_path(get_include_path().PATH_SEPARATOR.APP_ROOT.'/lib');
        
        // init config & hook
        Toby_Config::getInstance()->readDir(APP_ROOT.'/config');
        if(file_exists(APP_ROOT.'/hooks/config.hook.php')) include APP_ROOT.'/hooks/config.hook.php';
        
        // error handling
        error_reporting(E_ALL);
        ini_set('display_errors', Toby_Config::_getValue('toby', 'displayErrors') ? '1' : '0');
        
        // define web constants
        if($request == null)
        {
            define('REQUEST', false);
            
            define('APP_URL', false);
            define('SECURE_APP_URL', false);
            define('APP_URL_REL', false);
        }
        else
        {
            define('REQUEST', $request);
            
            define('APP_URL', Toby_Config::_hasKey('toby', 'appURL') ? Toby_Config::_getValue('toby', 'appURL') : '');
            define('SECURE_APP_URL', Toby_Config::_hasKey('toby', 'secureAppURL') ? Toby_Config::_getValue('toby', 'secureAppURL') : APP_URL);
            define('APP_URL_REL', preg_replace('/https?:\/\/(www\.)?[a-zA-Z0-9.-_]+\.[a-zA-Z]{2,4}\/?/', '/', APP_URL));
        }
        
        // init logging
        Toby_Logger::init(APP_ROOT.'/logs');
        Toby_Logger::logErrors('error');
        
        if(Toby_Config::_getValue('toby', 'logRequestTimes'))
        {
            self::$logRequestTime = true;
            Toby_Logger::log('[app start]', 'request-times', true);
        }
        
        // init mysql
        if(Toby_Config::_getValue('toby', 'logMySQLQueries', 'bool')) Toby_MySQL::getInstance()->initQueryLogging();
        
        // include init hook
        if(file_exists(APP_ROOT.'/hooks/init.hook.php')) include APP_ROOT.'/hooks/init.hook.php';
        
        // set encoding
        if(empty(self::$ENCODING)) self::setEncoding('UTF-8');
        
        // force resolve
        if(Toby_Config::_hasKey('toby', 'forceResolve')) $request = Toby_Config::_getValue('toby', 'forceResolve');
        
        // resolve and boot
        if($request != null)
        {
            // resolve
            $elements = explode('/', $request);

            $controllerName = !empty($elements[0]) ? strtolower($elements[0]) : 'index';
            $actionName = !empty($elements[1]) ? strtolower($elements[1]) : 'index';
            $vars = (count($elements) > 2) ? array_slice($elements, 2) : null;
            
            // boot
            Toby::boot($controllerName, $actionName, $vars, true);
        }
    }
    
    public static function boot($controllerName, $actionName = 'index', $vars = null, $stdResolveOnFail = false)
    {
        // run action
        $controller = self::runAction($controllerName, $actionName, $vars);
        
        if($controller === false)
        {
            // default resolve on fail
            if($stdResolveOnFail)
            {
                if(Toby_Config::_hasKey('toby', 'defaultResolve'))
                {
                    // reboot
                    list($controllerName, $actionName) = explode('/', Toby_Config::_getValue('toby', 'defaultResolve'));
                    self::boot($controllerName, $actionName);
                }
            }
        }
        else
        {
            // define resolve
            define('RESOLVE', "$controllerName/$actionName".($vars == null ? '' : '/'.implode('/', $vars)));
            
            // include resolved hook
            if(file_exists(APP_ROOT.'/hooks/resolved.hook.php')) include APP_ROOT.'/hooks/resolved.hook.php';
            
            // render
            echo self::renderController($controller);
        }
    }
    
    public static function runAction($controller, $action = 'index', $vars = null)
    {
        // start timing;
        self::requestTimeLogStart("$controller/$action".($vars === null ? '' : '/'.implode('/', $vars)));
        
        // vars
        $controllerFullName = 'Controller_'.strtoupper(substr($controller, 0, 1)).substr($controller, 1);
        $actionFullName = $action . 'Action';
        
        // exec
        if(file_exists(APP_ROOT."/controller/$controllerFullName.class.php"))
        {
            require_once APP_ROOT."/controller/$controllerFullName.class.php";
            
            if(class_exists($controllerFullName))
            {
                $controllerInstance = new $controllerFullName($controller, $action);
                
                if(method_exists($controllerInstance, $actionFullName))
                {
                    // call
                    if($vars == null) call_user_func(array($controllerInstance, $actionFullName));
                    else call_user_func_array (array($controllerInstance, $actionFullName), $vars);
                    
                    // stop timing
                    self::requestTimeLogStop(true);
                    
                    // return
                    return $controllerInstance;
                }
                else
                {
                    if($action !== 'default')
                    {
                        // stop timing
                        self::requestTimeLogStop(false);

                        // return
                        return self::runAction($controller, 'default', $vars);
                    }
                }
            }
        }
        
        // stop timing
        self::requestTimeLogStop(false);
        
        // return
        return false;
    }
    
    private static function requestTimeLogStart($title)
    {
        // cancellation
        if(self::$logRequestTime !== true) return;
        
        // store time and title
        self::$requestLogData[]  = array($title, microtime());
        
        // log
        Toby_Logger::log("running action: $title", 'request-times', true);
    }
    
    private static function requestTimeLogStop($success)
    {
        // cancellation
        if(self::$logRequestTime !== true) return;
        
        // get time and title
        list($title, $startTime) = array_pop(self::$requestLogData);
        
        // log
        $deltatime = number_format(microtime() - $startTime, 3);
        Toby_Logger::log("action done: $title [{$deltatime}ms]".($success ? '' : ' [action not found]'), 'request-times', true);
    }
    
    public static function renderController(Toby_Controller $controller)
    {
        // cancellation
        if(!$controller->renderView) return;
        
        // start timing
        if(self::$logRequestTime) $starttime = microtime();
        
        // prepare theme manager
        if(!Toby_ThemeManager::initByController($controller)) Toby::finalize('unable to set theme '.Toby_ThemeManager::$themeName);
        
        // render content
        $content = Toby_Renderer::renderPage($controller);
        
        // stop timing
        if(self::$logRequestTime)
        {
            $deltatime = number_format(microtime() - $starttime, 3);
            Toby_Logger::log("rendering controller: {$controller->serialize()} [{$deltatime}ms]", 'request-times', true);
        }
        
        // return
        return $content;
    }
    
    /* settings */
    public static function setEncoding($encoding)
    {
        // set
        self::$ENCODING = $encoding;
        
        // set mb
        mb_internal_encoding($encoding);
    }
    
    /* autoloader */
    public static function autoload($className)
    {
        // prepare
        $elements = explode('_', $className);
        
        if(count($elements) === 1) return;
        array_pop($elements);
        
        // resolve toby related
        if(strtolower($elements[0]) == 'toby')
        {
            $elements[0]    = TOBY_ROOT;
            $path = strtolower(implode('/', $elements))."/$className.class.php";
        }
        
        // resolve app related
        else
        {
            array_unshift($elements, APP_ROOT);
            $path = strtolower(implode('/', $elements))."/$className.class.php";
        }
        
        // require
        if(file_exists($path)) require_once($path);
    }
    
    /* finalization */
    public static function finalize($status = 0)
    {
        // complete time logging
        $count = count(self::$requestLogData);
        while($count > 0)
        {
            self::requestTimeLogStop(true);
            $count--;
        }
        
        if(self::$logRequestTime === true) Toby_Logger::log('[app end]', 'request-times', true);
        
        // exit
        if(is_int($status)) exit($status);
        else
        {
            echo Toby_Utils::printr($status);
            exit(1);
        }
    }
}