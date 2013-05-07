<?php

class Core_MySQL
{
    private static $instances   = array();

    private $link;
    private $host;
    private $user;
    private $pass;
    private $db;

    public $result;
    
    public $errorMessage;
    public $errorCode;

    public $mysqlCharset        = 'utf8';
    public $dryRun              = false;
    public $logQueries          = false;
    public $connected           = false;

    public static function getInstance($id = null)
    {
        if($id == null) $id = 'default';

        if(!isset(self::$instances[$id]))
        {
            self::$instances[$id] = new self();
            if($id == 'default') self::$instances[$id]->autoInit();
        }

        return self::$instances[$id];
    }

    private function autoInit()
    {
        $appConf = &Core_Config::_getConfig('toby');

        if(!isset($appConf['mysql']['host'])) return;
        if(!isset($appConf['mysql']['user'])) return;
        if(!isset($appConf['mysql']['password'])) return;

        $this->init(
            $appConf['mysql']['host'],
            $appConf['mysql']['user'],
            $appConf['mysql']['password'],
            isset($appConf['mysql']['db']) ? $appConf['mysql']['db'] : null
            );
    }

    public function init($host, $user, $pass, $db = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        if($db != null) $this->db   = $db;

        if($this->connected) $this->disconnect();
        return $this->connect();
    }

    public function connect()
    {
        $this->link = mysql_connect($this->host, $this->user, $this->pass);
        if($this->link === false) return false;

        if(isset($this->db)) if(!mysql_select_db($this->db, $this->link)) return false;

        mysql_set_charset($this->mysqlCharset, $this->link);

        $this->connected = true;
        return true;
    }

    public function disconnect()
    {
        mysql_close($this->link);
        $this->connected = false;
    }

    public function query($q)
    {
        // reset
        $this->result = false;
        if($this->errorMessage != '')   $this->errorMessage = '';
        if($this->errorCode != 0)       $this->errorCode = 0;

        // log
        if($this->logQueries) Core_Logger::log(str_replace(array("\n", "\r"), '', $q), 'mysql-queries', true);

        // dry run
        if($this->dryRun)
        {
            Core_Utils::printr($q);
            return true;
        }

        // query
        $result = @mysql_query($q, $this->link);
        
        // handle error
        if($result === false)
        {
            $this->errorMessage = mysql_error($this->link);
            $this->errorCode = mysql_errno($this->link);
            
            Core_Logger::log("[MYSQL ERROR] $this->errorCode: $this->errorMessage\nquery: $q", 'mysql-queries');
            return false;
        }

        // fetch result & return
        $this->result = $result;
        return true;
    }
    
    public function select($table, $fields = '*', $appendix = '')
    {
        $query = 'SELECT '.(is_array($fields) ? implode(',', $fields) : (string)$fields).' FROM '.$table.' '.$appendix.';';
        return $this->query($query);
    }

    public function insert($table, $data)
    {
        $query = 'INSERT INTO '.$table.' SET ';

        foreach($data as $key => $value) $query .= "`$key`={$this->verifyValue($value)}, ";
        $query = substr($query, 0, strlen($query) - 2).';';

        return $this->query($query);
    }

    public function update($table, $data, $appendix = '')
    {
        $query = 'UPDATE '.$table.' SET ';

        foreach($data as $key => $value) $query .= "`$key`={$this->verifyValue($value)}, ";
        $query = substr($query, 0, strlen($query) - 2).' '.$appendix.';';

        return $this->query($query);
    }

    public function replace($table, $data)
    {
        $query = 'REPLACE INTO '.$table.' SET ';

        foreach($data as $key => $value) $query .= "`$key`={$this->verifyValue($value)}, ";
        $query = substr($query, 0, strlen($query) - 2).';';

        return $this->query($query);
    }

    private function verifyValue($value)
    {
        if($value === null) return 'NULL';
        elseif(is_string($value)) return "'".mysql_real_escape_string($value)."'";
        else return $value;
    }

    public function delete($table, $appendix = '')
    {
        $query = 'DELETE FROM '.$table.' '.$appendix.';';
        return $this->query($query);
    }
    
    public function remove($table, $appendix = '')
    {
        return $this->delete($table, $appendix);
    }

    public function fetchElementByIndex($index = 0)
    {
        $row = mysql_fetch_row($this->result);
        return $row[$index];
    }

    public function fetchElementByName($name)
    {
        $assoc = mysql_fetch_assoc($this->result);
        return $assoc[$name];
    }

    public function fetchRow()
    {
        return mysql_fetch_row($this->result);
    }

    public function fetchRowSet()
    {
        $entries = array();
        while($row = mysql_fetch_row($this->result)) $entries[] = $row;

        return $entries;
    }

    public function fetchAssoc()
    {
        return mysql_fetch_assoc($this->result);
    }

    public function fetchAssocSet()
    {
        $entries = array();
        while($row = mysql_fetch_assoc($this->result)) $entries[] = $row;

        return $entries;
    }

    public function fetchObject()
    {
        return mysql_fetch_object($this->result);
    }

    public function fetchObjectSet()
    {
        $entries = array();
        while($row = mysql_fetch_object($this->result)) $entries[] = $row;

        return $entries;
    }

    public function getNumRows()
    {
        return mysql_num_rows($this->result);
    }

    public function getAffectedRows()
    {
        return mysql_affected_rows($this->result);
    }
    
    public function getInsertId()
    {
        return mysql_insert_id($this->link);
    }

    public function initQueryLogging()
    {
        $this->logQueries = true;
        Core_Logger::log('[MySQL log start] '.REQUEST, 'mysql-queries', true);
    }
}