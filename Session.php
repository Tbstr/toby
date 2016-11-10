<?php

namespace Toby;

use InvalidArgumentException;
use Logger;
use Toby\MySQL\MySQL;

class Session
{
    /* public variables */
    private $id                                 = null;
    
    private $opened                             = false;
    private $resumed                            = false;
    
    private $mysqlMode                          = false;

    /* public variables */
    private $SESSION                            = array();
    
    /** @var MySQL */
    private $mysql;

    /** @var Logger */
    private $logger;
    
    /* static variables */
    private static $instance                    = null;
    private static $updateCookieOnResume        = false;
    
    public  static $enabled                     = true;
    
    /* constants */
    const KEY                                   = 'tobysess';
    
    /* static getter */
    public static function getInstance($openOnInit = true)
    {
        if(self::$instance === null) self::$instance = new self($openOnInit);
        return self::$instance;
    }
    
    public function __construct($openOnInit = true)
    {
        // singleton check
        if(self::$instance !== null) Toby::getInstance()->finalize('Toby_Session is a Singleton dude. Use Toby_Session.getInstance().');

        $this->logger = Logger::getLogger("toby.session");
        
        // initialize
        $this->init();
        
        // open
        if($openOnInit) $this->open();
    }
    
    private function init()
    {
        // settings
        session_name('tobysess');

        $domain = preg_replace('/^https?:\/\/([^\/:]+)(:[0-9]+)?\/?.*$/', '$1', Toby::getInstance()->appURL);
        
        if(Config::get('toby.session.cookie.restrict_to_app_domain') === true)
        {
            ini_set('session.cookie_domain', $domain);
        }
        
        // set handlers
        if(Config::get('toby.session.mysql.enable'))
        {
            $this->mysqlMode = true;
            $this->mysql     = MySQL::getInstance();
            
            session_set_save_handler(
                array($this, 'handleMySQLSessionOpen'),
                array($this, 'handleMySQLSessionClose'),
                array($this, 'handleMySQLSessionRead'),
                array($this, 'handleMySQLSessionWrite'),
                array($this, 'handleMySQLSessionDestroy'),
                array($this, 'handleMySQLSessionClean')
                );
        }
    }
    
    public function open()
    {
        // cancellation
        if(self::$enabled !== true) return false;
        if($this->opened === true)  return true;
        
        // start
        if(session_start())
        {
            // get session id, regenerate if empty
            $this->id = session_id();
            
            if(empty($this->id))
            {
                if(session_regenerate_id(true)) $this->id = session_id();
                return false;
            }
            
            // session resume
            if(isset($_SESSION[self::KEY]))
            {
                // set vars
                $this->SESSION = $_SESSION;
                $this->resumed = true;
                
                // update session cookie for fresh ttl
                if(self::$updateCookieOnResume === true)
                {
                    setcookie(
                        self::KEY,
                        $this->id,
                        time() + ini_get("session.cookie_lifetime"),
                        ini_get("session.cookie_path"),
                        ini_get("session.cookie_domain"),
                        ini_get("session.cookie_secure"),
                        ini_get("session.cookie_httponly")
                    );
                }
            }
            
            // session start
            else
            {
                // set initial vars
                $this->SESSION[self::KEY] = array('last_seen' => 0);
            }
            
            // set vars
            $_SESSION       = false;
            $this->opened   = true;
            
            // return
            return true;
        }
        
        // return
        $this->logger->error('opening session failed');
        return false;
    }
    
    public function close()
    {
        // cancellation
        if(self::$enabled !== true) return false;
        if($this->opened === false) return true;
        
        // set last seen
        $this->set('last_seen', time());
        
        // write close
        $_SESSION = $this->SESSION;
        session_write_close();
        
        $this->opened = false;
        
        // return
        return true;
    }
    
    public function destroy()
    {
        session_destroy();
        $_SESSION       = false;
        $this->SESSION  = false;
        
        $this->opened   = false;
    }
    
    private function checkConnection($autoOpen = false)
    {
        // check var
        if($this->opened === false) return true;
        
        // auto open
        if($autoOpen === true) return $this->open();
        
        // return fail
        return false;
    }
    
    /* getter setter */
    public function getId()
    {
        return $this->id;    
    }
    
    public function isResumed()
    {
        return $this->resumed;
    }

    public function set($key, $value)
    {
        // check connection
        if($this->checkConnection(true) === false) return false;
        
        // set & return
        $this->SESSION[self::KEY][$key] = $value;
        return true;
    }
    
    public function arrAppend($key, $value)
    {
        // vars
        $arr = $this->has($key) ? $this->get($key) : array();
        if(!is_array($arr)) return false;
        
        // append
        $arr[] = $value;
        
        // set & return
        $this->set($key, $arr);
        return true;
    }
    
    public function arrRemove($key, $value)
    {
        // cancellation
        if(!$this->has($key)) return false;
        
        // get
        $arr = $this->get($key);
        if(!is_array($arr)) return false;
        
        // append
        foreach($arr as $arrKey => $arrVal)
        {
            if($arrVal === $value)
            {
                array_splice($arr, $arrKey, 1);
                return true;
            }
        }
        
        // set
        $this->set($key, $arr);
        
        // return fail
        return false;
    }

    public function get($key)
    {
        return $this->has($key) ? $this->SESSION[self::KEY][$key] : null;
    }

    public function getAll()
    {
        return $this->SESSION[self::KEY];
    }
    
    public function has($key)
    {
        return isset($this->SESSION[self::KEY][$key]);
    }
    
    public function delete($key)
    {
        // check connection
        if($this->checkConnection(true) === false) return false;
        
        // unset & return
        unset($this->SESSION[self::KEY][$key]);
        return true;
    }
    
    /* event handler */
    public function handleMySQLSessionOpen()
    {
        return $this->mysql->connected;
    }
    
    public function handleMySQLSessionClose()
    {
        return true;
    }
    
    public function handleMySQLSessionRead($id)
    {
        // cancellation
        if(empty($id)) return '';
        
        // fetch data
        $result = $this->mysql->select(Config::get('toby.session.mysql.table'), '*', sprintf("WHERE `id`='%s' LIMIT 1 FOR UPDATE", $this->mysql->esc($id)));
        if($result === false)               return '';
        if($result->getNumRows() === 0)     return '';
        
        // return
        return $result->fetchElementByName('data');
    }
    
    public function handleMySQLSessionWrite($id, $data)
    {
        // cancellation
        if(empty($id)) return false;

        // vars
        $time = time();
        
        // insert or update
        $this->mysql->insert(Config::get('toby.session.mysql.table'), array(
            'id'            => $id,
            'access_time'   => $time,
            'data'          => $data
        ), array(
            'access_time'   => $time,
            'data'          => $data
        ));
        
        // return result
        return $this->mysql->result;
    }
    
    public function handleMySQLSessionDestroy($id)
    {
        // cancellation
        if(empty($id)) return false;
        
        // query & return
        $this->mysql->delete(Config::get('toby.session.mysql.table'), sprintf("WHERE `id`='%s' LIMIT 1", $this->mysql->esc($id)));
        return $this->mysql->result;
    }
    
    public function handleMySQLSessionClean($maxLifeTime)
    {
        // clean db
        $this->mysql->delete(Config::get('toby.session.mysql.table'), 'WHERE `access_time`<'.(time() - (int)$maxLifeTime));
        
        // log & return fail
        if($this->mysql->result === false)
        {
            $this->logger->error('session garbage collection failed');
            return false;
        }
        
        // log & return success
        $this->logger->info('session garbage collection: '.$this->mysql->getNumAffected().' entries removed');
        return true;
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    /* to string */
    public function __toString()
    {
        return "Session[$this->id]";
    }
    
    /* static functionality */
    public static function setLifetime($lifetime, $updateCookieOnSessionResume = false)
    {
        // cancellation
        if(!is_int($lifetime)) throw new InvalidArgumentException('argument $lifetime is not of type integer');
        if(!is_bool($updateCookieOnSessionResume)) throw new InvalidArgumentException('argument $updateCookieOnSessionResume is not of type boolean');
        
        // set
        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.cookie_lifetime', $lifetime);
        
        // update on resume
        if($updateCookieOnSessionResume === true) self::$updateCookieOnResume = true;
    }
}
