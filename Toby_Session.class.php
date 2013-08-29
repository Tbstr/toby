<?php

class Toby_Session
{
    private static $instance = null;
    
    private $id             = -1;
    
    private $valid          = false;
    private $closed         = true;
    private $resumed        = false;
    
    private $mysqlMode      = false;
    private $mysql;

    private $key;
    private $SESSION;
    
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
        
        // open
        if($openOnInit) $this->open();
    }
    
    public function open()
    {
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
        
        // settings
        session_name('tobysess');
        ini_set('session.cookie_domain', preg_replace('/^https?:\/\//', '', APP_URL));
        
        // start
        if(session_start())
        {
            $this->id = session_id();
            $this->key = 'tobysess';
            
            // session resume
            if(isset($_SESSION[$this->key]))
            {
                $this->SESSION = $_SESSION;
                $this->resumed = true;
            }
            
            // session start
            else
            {
                $this->SESSION[$this->key] = array('last_seen' => 0);
            }
            
            // set vars
            $_SESSION = false;
            $this->valid = true;
            $this->closed = false;
        }
        else Toby_Logger::error('unable to start session');
    }
    
    public function close()
    {
        $this->set('last_seen', time());
        
        $_SESSION = $this->SESSION;
        session_write_close();
        
        $this->closed = true;
    }
    
    public function destroy()
    {
        session_destroy();
        $_SESSION = false;
        $this->SESSION = false;
        
        $this->closed = true;
    }
    
    /* getter setter */
    public function getId()
    {
        return $this->id;    
    }
    
    public function isValid()
    {
        return $this->valid;
    }
    
    public function isResumed()
    {
        return $this->resumed;
    }

    public function set($key, $value)
    {
        if($this->valid) if(!$this->closed) $this->SESSION[$this->key][$key] = $value;
    }

    public function get($key)
    {
        return $this->has($key) ? $this->SESSION[$this->key][$key] : null;
    }
    
    public function has($key)
    {
        return isset($this->SESSION[$this->key][$key]);
    }
    
    public function delete($key)
    {
        unset($this->SESSION[$this->key][$key]);
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