<?php

class Toby_Security
{
    const XSRFKeyName   = 'xsrfkey';
    
    /* initialization */
    public static function init()
    {
        // add XSRF Key if missing
        $session = Toby_Session::getInstance();
        if(!$session->has(self::XSRFKeyName)) $session->set(self::XSRFKeyName, Toby_Utils::randomChars(32));
    }
    
    /* XSRF */
    public static function XSRFUpdateKey()
    {
        Toby_Session::getInstance()->set(self::XSRFKeyName, Toby_Utils::randomChars(32));
    }
    
    public static function XSRFGetKey()
    {
        return Toby_Session::getInstance()->get(self::XSRFKeyName);
    }
    
    public static function XSRFFormElement()
    {
        return '<input type="hidden" name="'.self::XSRFKeyName.'" value="'.self::XSRFGetKey().'" />';
    }
    
    public static function XSRFValidateKey($key = false)
    {
        // get key
        if($key === false)
        {
            if(isset($_POST[self::XSRFKeyName])) $key = $_POST[self::XSRFKeyName];
            elseif(isset($_GET[self::XSRFKeyName])) $key = $_GET[self::XSRFKeyName];
            else
            {
                // log
                Toby_Logger::error('XSRF validation failed due to missing key');

                // hang up
                Toby::finalize();
            }
        }
        
        // validate
        if($key !== self::XSRFGetKey())
        {
            // log
            Toby_Logger::error('XSRF violation for request: '.REQUEST.(empty($_GET) ? '' : ' GET:'.http_build_query($_GET)).(empty($_POST) ? '' : ' POST:'.http_build_query($_POST))).' IP:'.$_SERVER['REMOTE_ADDR'];
            
            // hang up
            Toby::finalize();
        }
    }
}
