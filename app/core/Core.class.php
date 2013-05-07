<?php

// autoloader
function autoload($className)
{
    // name mangler
    $elements = explode('_', $className);
    array_pop($elements);
    
    $path = 'app/'.strtolower(implode('/', $elements))."/$className.class.php";
    
    if(file_exists($path)) require_once($path);
}
spl_autoload_register('autoload');

// add lib to include path
set_include_path(get_include_path().PATH_SEPARATOR.dirname(__DIR__).'/lib');

// core class
class Core
{
    private static $tobyConf;
    private static $logRequestTime = false;
    
    public static function init($request = null)
    {
        // error handling
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        // define constants
        define('DS', DIRECTORY_SEPARATOR);
        define('NL', "\n");

        define('APP_ROOT', getcwd());

        // init config
        Core_Config::getInstance()->readDir(APP_ROOT.'/app/config');
        
        self::$tobyConf = &Core_Config::_getConfig('toby');

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
            
            define('APP_URL', isset(self::$tobyConf['appURL']) ? self::$tobyConf['appURL'] : '');
            define('SECURE_APP_URL', isset(self::$tobyConf['secureAppURL']) ? self::$tobyConf['secureAppURL'] : APP_URL);
            define('APP_URL_REL', dirname($_SERVER['SCRIPT_NAME']));
        }
        
        // init logging
        Core_Logger::init(APP_ROOT.'/app/logs');
        Core_Logger::logErrors('error');
        
        if(Core_Config::_getValue('toby', 'logRequestTimes', 'bool'))
        {
            self::$logRequestTime = true;
            Core_Logger::log('[app start]', 'request-times', true);
        }
        
        // init mysql
        if(Core_Config::_getValue('toby', 'logMySQLQueries', 'bool')) Core_MySQL::getInstance()->initQueryLogging();
        
        // include init hook
        if(file_exists(APP_ROOT.'/app/hooks/init.hook.php')) include APP_ROOT.'/app/hooks/init.hook.php';
        
        // resolve and boot
        if($request != null)
        {
            // resolve
            $elements = explode('/', $request);

            $controllerName = !empty($elements[0]) ? strtolower($elements[0]) : 'index';
            $actionName = !empty($elements[1]) ? strtolower($elements[1]) : 'index';
            $vars = (count($elements) > 2) ? array_slice($elements, 2) : null;
            
            // boot
            Core::boot($controllerName, $actionName, $vars, true);
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
                if(!empty(self::$tobyConf['defaultResolve']))
                {
                    // reboot
                    list($controllerName, $actionName) = explode('/', self::$tobyConf['defaultResolve']);
                    self::boot($controllerName, $actionName);
                }
            }
        }
        else
        {
            // define resolve
            define('RESOLVE', "$controllerName/$actionName".($vars == null ? '' : '/'.implode('/', $vars)));
            
            // include resolved hook
            if(file_exists(APP_ROOT.'/app/hooks/resolved.hook.php')) include APP_ROOT.'/app/hooks/resolved.hook.php';
            
            // render
            echo self::renderController($controller);
        }
    }
    
    public static function runAction($controller, $action = 'index', $vars = null)
    {
        // start timing
        if(self::$logRequestTime)
        {
            $starttime = microtime();
            Core_Logger::log("running action: $controller/$action".($vars == null ? '' : '/'.implode('/', $vars)), 'request-times', true);
        }
        
        // vars
        $controllerFullName = 'Controller_'.strtoupper(substr($controller, 0, 1)).substr($controller, 1);
        $actionFullName = $action . 'Action';
        
        // exec
        if(file_exists(APP_ROOT."/app/controller/$controllerFullName.class.php"))
        {
            require_once APP_ROOT."/app/controller/$controllerFullName.class.php";
            
            if(class_exists($controllerFullName))
            {
                $controllerInstance = new $controllerFullName($controller, $action);
                
                if(method_exists($controllerInstance, $actionFullName))
                {
                    // call
                    if($vars == null) call_user_func(array($controllerInstance, $actionFullName));
                    else call_user_func_array (array($controllerInstance, $actionFullName), $vars);
                    
                    // stop timing
                    if(self::$logRequestTime)
                    {
                        $deltatime = number_format(microtime() - $starttime, 3);
                        Core_Logger::log("action done: $controller/$action".($vars == null ? '' : '/'.implode('/', $vars))." [{$deltatime}ms]", 'request-times', true);
                    }
                    
                    // return
                    return $controllerInstance;
                }
            }
        }
        
        // stop timing
        if(self::$logRequestTime)
        {
            $deltatime = number_format(microtime() - $starttime, 3);
            Core_Logger::log("action done: $controller/$action".($vars == null ? '' : '/'.implode('/', $vars))." [{$deltatime}ms] [action not found]", 'request-times', true);
        }
        
        // return
        return false;
    }
    
    public static function renderController(Core_Controller &$controller)
    {
        // cancellation
        if(!$controller->renderView) return;
        
        // start timing
        if(self::$logRequestTime) $starttime = microtime();
        
        // prepare theme manager
        if(!Core_ThemeManager::initByController($controller)) exit('unable to set theme '.Core_ThemeManager::$themeName);
        
        // render content
        $content = Core_Renderer::renderPage($controller);
        
        // stop timing
        if(self::$logRequestTime)
        {
            $deltatime = number_format(microtime() - $starttime, 3);
            Core_Logger::log("rendering controller: {$controller->serialize()} [{$deltatime}ms]", 'request-times', true);
        }
        
        // return
        return $content;
    }
}