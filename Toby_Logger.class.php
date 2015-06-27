<?php

class Toby_Logger
{
    /* public variables */
    public static $initialized              = false;
    public static $logsDirPath;
    
    public static $enabled                  = true;

    /* private variables */
    private static $logErrors               = false;
    private static $fatalNotificationTo;
    private static $listeners               = array();

    private static $prefix                  = '';
    private static $buffer                  = array();

    /* constants */
    const TYPE_DEFAULT                      = 'type_default';
    const TYPE_NOTICE                       = 'type_notice';
    const TYPE_WARNING                      = 'type_warning';
    const TYPE_ERROR                        = 'type_error';
    const TYPE_EXCEPTION                    = 'type_exception';

    /* initialization */
    public static function init($logsDirPath)
    {
        self::$logsDirPath          = $logsDirPath;
        self::$fatalNotificationTo  = Toby_Config::get('toby')->getValue('fatalNotificationTo', 'string');
        
        self::$initialized          = true;
    }

    /* root logging functions */
    private static function _log($content, $log = 'sys', $omitSys = false)
    {
        if(!self::$initialized) return;
        if(!self::$enabled) return;
        
        // writer to buffer
        if(!isset(self::$buffer[$log])) self::$buffer[$log] = '';
        self::$buffer[$log] .= '- ['.date('d.m.Y H:i:s').']   '.self::$prefix.$content."\n";
        
        // main log
        if($log !== 'sys' && !$omitSys) self::_log($content, 'sys');
    }
    
    public static function flushBuffer()
    {
        // write buffer contents
        foreach(self::$buffer as $log => $content)
        {
            $fileHandle = @fopen(self::$logsDirPath."/$log.log", 'a');
            if($fileHandle === false) continue;

            fwrite($fileHandle, $content);
            fclose($fileHandle);
        }
        
        // reset buffer
        self::$buffer = array();
    }

    /* log wrapper */
    public static function log($content, $log = 'sys', $omitSys = false)
    {
        self::_log($content.self::traceStamp(), $log, $omitSys);
        self::callLogListener(self::TYPE_DEFAULT, $content);
    }

    public static function notice($content)
    {
        self::_log('[NOTICE] '.$content.self::traceStamp());
        self::callLogListener(self::TYPE_NOTICE, $content);
    }

    public static function warn($content)
    {
        self::_log('[WARNING] '.$content.self::traceStamp(), 'error');
        self::callLogListener(self::TYPE_WARNING, $content);
    }

    public static function error($content)
    {
        self::_log("[ERROR] $content".self::traceStamp(), 'error');
        self::callLogListener(self::TYPE_ERROR, $content);
    }

    public static function exception(Exception $e)
    {
        // handle previous
        $previous = $e->getPrevious();
        if(!empty($previous)) self::exception($previous);

        // log
        self::_log('[EXCEPTION] '.$e->getMessage()."\n".self::formatBackTrace($e->getTrace())."\n > ".$e->getFile().':'.$e->getLine(), 'error');
        self::callLogListener(self::TYPE_EXCEPTION, $e->getMessage());
    }

    /* system error logging */
    public static function logErrors()
    {
        if(!self::$logErrors)
        {
            set_error_handler('Toby_Logger::handleError', error_reporting());
            set_exception_handler('Toby_Logger::exception');
            register_shutdown_function('Toby_Logger::handleShutdown');
            
            self::$logErrors = true;
        }
    }

    /* helpers */
    public static function traceStamp()
    {
        $dbt = debug_backtrace();
        $entry = $dbt[1];
        
        return " > {$entry['file']}:{$entry['line']}";
    }

    public static function formatBackTrace($backtrace)
    {
        if(!is_array($backtrace)) throw new InvalidArgumentException('argument backtrace is not of type array');

        $lines = array();
        $count = count($backtrace);

        foreach($backtrace as $t)
        {
            $line = $count.': ';
            $count--;

            // class & function
            if(isset($t['class']))  $line .= $t['class'].'::'.$t['function'];
            else                    $line .= $t['function'];

            // arguments
            if($t['args'])
            {
                $argsElm = array();
                foreach($t['args'] as $arg)
                {
                    if(is_object($arg))
                    {
                        if(method_exists($arg, '__toString'))   $argsElm[] .= (string)$arg;
                        else                                    $argsElm[] .= 'Object';
                    }
                    elseif(is_array($arg))      $argsElm[] = 'Array['.count($arg).']';
                    else                        $argsElm[] = (string)$arg;
                }

                $line .= '('.implode(', ', $argsElm).')';
            }
            else $line .= '()';

            // file & line
            if(isset($t['file'])) $line .= ' > '.$t['file'].':'.$t['line'];

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /* getter setter */
    public static function getPrefix()
    {
        return self::$prefix;
    }

    public static function setPrefix($prefix)
    {
        self::$prefix = $prefix;
    }
    
    /* listeners */
    public static function setLogListener($type, $callback)
    {
        self::$listeners[] = array($type, $callback);
        Toby_Logging::setLogListener($type, $callback);
    }
    
    private static function callLogListener($type, $content)
    {
        foreach(self::$listeners as $listener)
        {
            if($listener[0] === $type) call_user_func($listener[1], $content);
        }
    }
    
    /* event handler */
    public static function handleError($errno, $errstr, $errfile = '', $errline = 0, $errcontex = array())
    {
        // vars
        $errLogMsg = "$errstr > $errfile:$errline";
        
        // typize & log
        switch($errno)
        {
            case E_RECOVERABLE_ERROR:
                $errLogMsg = '[FATAL_ERROR] '.$errLogMsg;
                self::callLogListener(self::TYPE_ERROR, $errLogMsg);
                break;
            
            case E_USER_ERROR:
            case E_ERROR:
                $errLogMsg = '[ERROR] '.$errLogMsg;
                self::callLogListener(self::TYPE_ERROR, $errLogMsg);
                break;
            
            case E_USER_WARNING:
            case E_WARNING:
                $errLogMsg = '[WARNING] '.$errLogMsg;
                self::callLogListener(self::TYPE_WARNING, $errLogMsg);
                break;
            
            case E_USER_NOTICE:
            case E_NOTICE:
                $errLogMsg = '[NOTICE] '.$errLogMsg;
                self::callLogListener(self::TYPE_NOTICE, $errLogMsg);
                break;
            
            default:
                $errLogMsg = '[UNKNOWN] '.$errLogMsg;                
                break;
        }
        
        // log
        self::_log($errLogMsg, 'error');
        
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
                // msg
                $logMsg = "[FATAL ERROR] $error[message] > $error[file]:$error[line]";
                
                // log
                self::_log($logMsg, 'error');
                
                // call callback
                self::callLogListener(self::TYPE_ERROR, $logMsg);
                
                // create backtrace & send mail
                if(!empty(self::$fatalNotificationTo)) mail(self::$fatalNotificationTo, 'Fatal Error', date('d.m.Y H:i:s')." $error[message] > $error[file]:$error[line]");
            }
        }
        
        // flush buffer
        self::flushBuffer();
    }
}