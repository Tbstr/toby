<?php

class Toby_Session
{
    /* statics */
    private static $instance    = null;
    public  static $enabled     = true;
    
    /* variables */
    private $id                 = -1;
    
    private $opened             = false;
    private $resumed            = false;
    
    private $mysqlMode          = false;
    private $mysql;

    private $SESSION            = array();
    
    /* constants */
    const KEY                   = 'tobysess';
    
    /* static getter */
    public static function getInstance($openOnInit = true)
    {
        if(self::$instance === null) self::$instance = new self($openOnInit);
        return self::$instance;
    }
    
    public function __construct($openOnInit = true)
    {
        // singleton check
        if(self::$instance !== null) Toby::finalize('Toby_Session is a Singleton dude. Use Toby_Session.getInstance().');
        
        // initialize
        $this->init();
        
        // open
        if($openOnInit) $this->open();
    }
    
    private function init()
    {
        // settings
        session_name('tobysess');
        ini_set('session.cookie_domain', preg_replace('/^https?:\/\//', '', Toby::getInstance()->appURL));
        
        // set handlers
        if(Toby_Config::_getValue('toby', 'sessionUseMySQL', 'bool'))
        {
            $this->mysqlMode = true;
            $this->mysql = Toby_MySQL::getInstance();
            
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
            $this->id = session_id();
            
            // session resume
            if(isset($_SESSION[self::KEY]))
            {
                $this->SESSION = $_SESSION;
                $this->resumed = true;
            }
            
            // session start
            else
            {
                $this->SESSION[self::KEY] = array('last_seen' => 0);
            }
            
            // set vars
            $_SESSION       = false;
            $this->opened   = true;
            
            // return
            return true;
        }
        
        // return
        Toby_Logger::error('opening session failed');
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
        $_SESSION = false;
        $this->SESSION = false;
        
        $this->opened = false;
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

    public function get($key)
    {
        return $this->has($key) ? $this->SESSION[self::KEY][$key] : null;
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
    
    public function printr()
    {
        Toby_Utils::printr($this->SESSION);
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
        $id = mysql_real_escape_string($id);
        
        $this->mysql->select('pd_sessions', '*', "WHERE id='$id' FOR UPDATE");
        if($this->mysql->getNumRows() != 0) return $this->mysql->fetchElementByName('data');
        
        return '';
    }
    
    public function handleMySQLSessionWrite($id, $data)
    {
        $this->mysql->replace('pd_sessions', array(
            'id' => $id,
            'access_time' => time(),
            'data' => $data
        ));
        
        return $this->mysql->result;
    }
    
    public function handleMySQLSessionDestroy($id)
    {
        $id = mysql_real_escape_string($id);
        
        $this->mysql->delete('pd_sessions', "WHERE id='$id'");
        return $this->mysql->result;
    }
    
    public function handleMySQLSessionClean($max)
    {
        $old = mysql_real_escape_string(time() - $max);
        
        $this->mysql->delete('pd_sessions', "WHERE access_time < $old");
        return $this->mysql->result;
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    /* to string */
    public function __toString()
    {
        return "Toby_Session[$this->id]";
    }
}