<?php

namespace Toby\Utils;

use Toby\Autoloader;
use Toby\Config;

class TobyUtils
{
    public static function isDefinedRequest($request, $omitAction = false)
    {
        // check aliases
        if(Config::has('toby.request.aliases'))
        {
            $requestAliases = Config::get('toby.request.aliases');
            if(isset($requestAliases[$request])) return true;
        }
        
        // check controller
        $elements = explode('/', $request);

        $controllerName = !empty($elements[0]) ? strtolower($elements[0]) : null;
        $actionName     = !empty($elements[1]) ? strtolower($elements[1]) : 'index';
        
        if($controllerName !== null)
        {
            // check controller instance
            $controllerInstance = Autoloader::getControllerInstance($controllerName, $actionName, null);
            
            if($controllerInstance !== null)
            {
                // check action
                if($omitAction) return true;

                $actionMethodName = $actionName.'Action';
                if(method_exists($controllerInstance, $actionMethodName)) return true;
            }
        }
        
        return false;
    }
}
