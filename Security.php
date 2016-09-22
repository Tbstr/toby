<?php

namespace Toby;

use Logger;
use Toby\Utils\StringUtils;

class Security
{
    /* constants */
    const XSRFKeyName = 'xsrfkey';
    
    /* initialization */
    public static function init()
    {
        // add XSRF Key if missing
        $session = Session::getInstance();
        if(!$session->has(self::XSRFKeyName)) $session->set(self::XSRFKeyName, StringUtils::randomChars(32));
    }
    
    /* XSRF */
    public static function XSRFUpdateKey()
    {
        Session::getInstance()->set(self::XSRFKeyName, StringUtils::randomChars(32));
    }
    
    public static function XSRFGetKey()
    {
        return Session::getInstance()->get(self::XSRFKeyName);
    }
    
    public static function XSRFFormElement()
    {
        return '<input type="hidden" name="'.self::XSRFKeyName.'" value="'.self::XSRFGetKey().'" />';
    }
    
    public static function XSRFValidateKey($key = null, $finalizeOnFail = true)
    {
        // get key
        if(empty($key))
        {
            if(isset($_POST[self::XSRFKeyName]))    $key = $_POST[self::XSRFKeyName];
            elseif(isset($_GET[self::XSRFKeyName])) $key = $_GET[self::XSRFKeyName];
            else
            {
                // log
                Logger::getLogger("toby.security")->info('XSRF validation failed due to missing key. REQUEST: '
                    .Toby::getInstance()->request
                    .(empty($_GET) ? '' : ' GET:'.http_build_query($_GET))
                    .(empty($_POST) ? '' : ' POST:'.http_build_query($_POST))
                    .' IP:'.$_SERVER['REMOTE_ADDR']);

                // hang up or return
                if($finalizeOnFail === true)
                {
                    Toby::finalize();
                }
                else
                {
                    return false;
                }
            }
        }
        
        // validate & return on success
        if($key === self::XSRFGetKey()) return true;
        
        // log fail
        Logger::getLogger("toby.security")->info('XSRF violation. REQUEST: '
            .Toby::getInstance()->request
            .(empty($_GET) ? '' : ' GET:'.http_build_query($_GET))
            .(empty($_POST) ? '' : ' POST:'.http_build_query($_POST))
            .' IP:'.$_SERVER['REMOTE_ADDR']);

        // hang up or return
        if($finalizeOnFail) Toby::finalize();
        return false;
    }
}
