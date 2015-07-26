<?php

class Toby_Logging
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
        self::$fatalNotificationTo  = Toby_Config::get('toby')->getValue('fatalNotificationTo', 'string');

        $configFilenames = array(
            APP_ROOT.'/config/override/logging.php',
            APP_ROOT.'/config/logging.php',
        );
        $config = null;
        foreach ($configFilenames as $configFilename)
        {
            if (file_exists($configFilename))
            {
                $config = include($configFilename);
                break;
            }
        }
        if ($config === null || !is_array($config))
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

        set_error_handler('Toby_Logging::handleError', error_reporting());
        set_exception_handler('Toby_Logging::handleException');
        register_shutdown_function('Toby_Logging::handleShutdown');
    }

    private static function interpolateVariablesInConfig(array &$config)
    {
        $variables = array(
            "{APP_ROOT}" => APP_ROOT,
        );

        foreach (Toby_Config::get("logging")->getAllValues() as $key => $value)
        {
            $variables['{' . $key . '}'] = $value;
        }

        foreach ($config as $key => &$value)
        {
            if (is_array($value))
            {
                self::interpolateVariablesInConfig($value);
            }
            elseif (is_string($value))
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

    public static function handleException(Exception $e)
    {
        \LoggerMDC::put("file", $e->getFile());
        \LoggerMDC::put("line", $e->getLine());
        self::$errorLogger->fatal("unhandled exception at " . $e->getFile() . ":" . $e->getLine(), $e);
        self::callLogListener(self::TYPE_EXCEPTION, $e->getMessage());
    }

    /* event handler */
    public static function handleError($errno, $errstr, $errfile = '', $errline = 0, $errcontex = array())
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
                if(!empty(self::$fatalNotificationTo)) mail(self::$fatalNotificationTo, 'Fatal Error', date('d.m.Y H:i:s').$logMsg);
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
        return \Logger::getLogger(str_replace('_', '.', strtolower($className)));
    }
}