<?php

class Toby_Logger
{
    public static $initialized = false;
    public static $logsDirPath;
    
    private static $logErrors = false;
    
    private static $fatalNotificationTo;
    
    public static $enabled = true;
    
    public static function init($logsDirPath)
    {
        self::$logsDirPath = $logsDirPath;
        self::$fatalNotificationTo = Toby_Config::_getValue('toby', 'fatalNotificationTo', 'string');
        
        self::$initialized = true;
    }
    
    public static function log($content, $log = 'sys', $omitSys = false)
    {
        if(!self::$initialized) return;
        if(!self::$enabled) return;
        
        $fileHandle = @fopen(self::$logsDirPath."/$log.log", 'a');
        if($fileHandle == false) return;
        
        fwrite($fileHandle, '- ['.date('d.m.Y H:i:s').']   '.$content."\n");
        fclose($fileHandle);
        
        // main log
        if($log != 'sys' && !$omitSys) self::log($content, 'sys');
    }
    
    public static function error($content)
    {
        self::log("[ERROR] $content".self::traceStamp(), 'error');
    }
    
    public static function warn($content)
    {
        self::log('[WARNING] '.$content.self::traceStamp(), 'error');
    }
    
    public static function notice($content)
    {
        self::log('[NOTICE] '.$content.self::traceStamp());
    }
    
    public static function logErrors()
    {
        if(!self::$logErrors)
        {
            set_error_handler('Toby_Logger::handleError', error_reporting());
            set_exception_handler('Toby_Logger::handleException');
            register_shutdown_function('Toby_Logger::handleShutdown');
            
            self::$logErrors = true;
        }
    }
    
    public static function traceStamp()
    {
        $dbt = debug_backtrace();
        $entry = $dbt[1];
        
        return " > {$entry['file']}:{$entry['line']}";
    }
    
    public static function rotate()
    {
        Toby_Logger::log('log rotate');
    }
    
    /* event handler */
    public static function handleError($errno, $errstr, $errfile = '', $errline = 0, $errcontex = array())
    {
        $errCode = '';
        switch ($errno)
        {
            case E_RECOVERABLE_ERROR:
                $errCode = 'FATAL ERROR';
                break;
            
            case E_USER_ERROR:
            case E_ERROR:
                $errCode = 'ERROR';
                break;
            
            case E_USER_WARNING:
            case E_WARNING:
                $errCode = 'WARNING';
                break;
            
            case E_USER_NOTICE:
            case E_NOTICE:
                $errCode = 'NOTICE';
                break;
            
            default:
                $errCode = 'UNKNOWN';
                break;
        }
        
        self::log("[$errCode] $errstr > $errfile:$errline", 'error');
        
        return false;
    }

    public static function handleException($exception)
    {
        self::log("[EXCEPTION] $exception", 'error');
    }
    
    public static function handleShutdown()
    {
        $error = error_get_last();
        
        if($error != null)
        {
            if($error['type'] == 1)
            {
                // log
                self::log("[FATAL ERROR] $error[message] > $error[file]:$error[line]", 'error');
                
                // send mail
                if(APP_URL !== false)
                {
                    if($_SERVER['HTTP_HOST'] != 'localhost' && $_SERVER['HTTP_HOST'] != '127.0.0.1')
                    {
                        if(!empty(self::$fatalNotificationTo)) mail(self::$fatalNotificationTo, 'Fatal Error', "$error[message] > $error[file]:$error[line]");
                    }
                }
            }
        }
    }
}