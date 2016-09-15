<?php

namespace Toby\Logging;

use Toby\Config;

class Logging
{
    private static $fatalNotificationTo;
    private static $listeners               = array();

    /**
     * @var \Logger
     */
    public static $tobyLogger;
    /**
     * @var \Logger
     */
    public static $errorLogger;

    /* constants */
    const TYPE_DEFAULT                      = 'type_default';
    const TYPE_NOTICE                       = 'type_notice';
    const TYPE_WARNING                      = 'type_warning';
    const TYPE_ERROR                        = 'type_error';
    const TYPE_EXCEPTION                    = 'type_exception';

    public static function init()
    {
        self::$fatalNotificationTo = Config::get('toby.error.fatal_notificationT_to');

        $config = Config::get('logging.config');
        if(!is_array($config) || empty($config))
        {
            trigger_error("unable to configure logging", E_USER_ERROR);
            // this will throw an ERROR and ends execution
        }
        else
        {
            self::interpolateVariablesInConfig($config);
            \Logger::configure($config);
            self::$tobyLogger = \Logger::getLogger("toby");
            self::$errorLogger = \Logger::getLogger("error");
        }

        set_error_handler('\Toby\Logging\Logging::handleError', error_reporting());
        set_exception_handler('\Toby\Logging\Logging::handleException');
        register_shutdown_function('\Toby\Logging\Logging::handleShutdown');
    }

    private static function interpolateVariablesInConfig(array &$config, array &$variables = null)
    {
        // init variables
        if(empty($variables))
        {
            $variables = array( "{APP_ROOT}" => APP_ROOT );
            foreach(Config::get('logging.config_vars') as $key => $value) { $variables['{' . $key . '}'] = $value; }
        }

        // crawl config
        foreach($config as $key => &$value)
        {
            if(is_array($value))
            {
                self::interpolateVariablesInConfig($value, $variables);
            }
            elseif(is_string($value))
            {
                $value = str_replace(array_keys($variables), array_values($variables), $value);
            }
        }
    }

    /* listeners */
    public static function setLogListener($type, $callback)
    {
        self::$listeners[] = array($type, $callback);
    }

    private static function callLogListener($type, $content)
    {
        foreach(self::$listeners as $listener)
        {
            if($listener[0] === $type) call_user_func($listener[1], $content);
        }
    }

    /* event handler */
    public static function handleException(\Exception $e)
    {
        \LoggerMDC::put("file", $e->getFile());
        \LoggerMDC::put("line", $e->getLine());
        self::$errorLogger->fatal("unhandled exception at " . $e->getFile() . ":" . $e->getLine(), $e);
        self::callLogListener(self::TYPE_EXCEPTION, $e->getMessage());
    }

    public static function handleError($errno, $errstr, $errfile = '', $errline = 0/*, $errcontex = array()*/)
    {
        \LoggerMDC::put("file", $errfile);
        \LoggerMDC::put("line", $errline);

        // vars
        $errLogMsg = "'$errstr' at $errfile:$errline";

        // typize & log
        switch($errno)
        {
            case E_RECOVERABLE_ERROR:
                self::$errorLogger->error($errLogMsg);
                self::callLogListener(self::TYPE_ERROR, $errLogMsg);
                break;

            case E_USER_ERROR:
            case E_ERROR:
                self::$errorLogger->error($errLogMsg);
                self::callLogListener(self::TYPE_ERROR, $errLogMsg);
                break;

            case E_USER_WARNING:
            case E_WARNING:
            case E_STRICT:
            case E_USER_DEPRECATED:
            case E_DEPRECATED:
                self::$errorLogger->warn($errLogMsg);
                self::callLogListener(self::TYPE_WARNING, $errLogMsg);
                break;

            case E_USER_NOTICE:
            case E_NOTICE:
                self::$errorLogger->info($errLogMsg);
                self::callLogListener(self::TYPE_NOTICE, $errLogMsg);
                break;

            default:
                self::$errorLogger->error("unknown error $errLogMsg");
                break;
        }

        \LoggerMDC::remove("file");
        \LoggerMDC::remove("line");

        // return
        return false;
    }

    public static function handleShutdown()
    {
        // check for fatal error before shutdown
        $error = error_get_last();
        if($error !== null)
        {
            if($error['type'] === 1)
            {
                \LoggerMDC::put("file", $error['file']);
                \LoggerMDC::put("line", $error['line']);
                // msg
                $logMsg = "[FATAL ERROR] {$error['message']} at {$error['file']}:{$error['line']}";

                // log
                self::$errorLogger->fatal($logMsg);

                // call callback
                self::callLogListener(self::TYPE_ERROR, $logMsg);

                // create backtrace & send mail
                if(!empty(self::$fatalNotificationTo))
                {
                    mail(implode(', ', self::$fatalNotificationTo), 'Fatal Error', date('d.m.Y H:i:s').$logMsg);
                }
            }
        }
    }

    /**
     * @param mixed $classOrObject
     * @return \Logger
     */
    public static function logger($classOrObject)
    {
        $className = is_object($classOrObject) ? get_class($classOrObject) : $classOrObject;
        $loggerName = trim(str_replace(array('_', '\\'), '.', strtolower($className)), '.');
        return \Logger::getLogger($loggerName);
    }
}
