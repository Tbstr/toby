<?php

class Toby_MySQL
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
    
    private $queryRec           = false;
    private $queryRecBuffer;

    public static function getInstance($id = null)
    {
        if($id === null) $id = 'default';

        if(!isset(self::$instances[$id]))
        {
            self::$instances[$id] = new self();
            if($id === 'default') self::$instances[$id]->autoInit();
        }

        return self::$instances[$id];
    }
    
    /* init */
    private function autoInit()
    {
        // cancellation
        if(Toby_Config::_getValue('toby', 'mySQLAutoConnect') !== true) return;
        
        // vars
        $mad = Toby_Config::_getValue('toby', 'mySQLAccessData');

        $this->init(
            $mad['host'],
            $mad['user'],
            $mad['password'],
            isset($mad['db']) ? $mad['db'] : null
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
        // reset connected state
        $this->connected = false;
        
        // connect
        $this->link = mysql_connect($this->host, $this->user, $this->pass);
        if($this->link === false) return false;

        // select db
        if(isset($this->db)) if(!mysql_select_db($this->db, $this->link))
        {
            mysql_close($this->link);
            return false;
        }

        // set charset
        mysql_set_charset($this->mysqlCharset, $this->link);

        // set & return
        $this->connected = true;
        return true;
    }

    public function disconnect()
    {
        mysql_close($this->link);
        $this->connected = false;
    }
    
    /* query methods */
    public function query($q)
    {
        // reset
        $this->result       = false;
        $this->errorMessage = '';
        $this->errorCode    = 0;

        // log & rec
        if($this->logQueries) Toby_Logger::log(str_replace(array("\n", "\r"), '', $q), 'mysql-queries', true);
        
        if($this->queryRec === true)
        {
            $dbt = debug_backtrace();
            $dbtEntry = false;
            
            for($i = 0, $c = count($dbt); $i < $c; $i++)
            {
                if($dbt[$i]['class'] !== 'Toby_MySQL')
                {
                    $dbtEntry = $dbt[$i];
                    break;
                }
            }
            
            if($dbtEntry !== false) $this->queryRecBuffer[] = "{$dbtEntry['file']}:{$dbtEntry['line']}";
            $this->queryRecBuffer[] = str_replace(array("\n", "\r"), '', $q)."\n";
        }

        // dry run
        if($this->dryRun)
        {
            Toby_Utils::printr($q);
            return true;
        }
        
        // auto escape
        $q = preg_replace_callback('/esc\[([^\[\]]*)\]/', array($this, 'autoEscapeCallback'), $q);
        
        // query
        $result = mysql_query($q, $this->link);
        
        // handle error
        if($result === false)
        {
            // set error vars
            $this->errorMessage     = mysql_error($this->link);
            $this->errorCode        = mysql_errno($this->link);
            
            // fetch errors
            switch($this->errorCode)
            {
                // gone away
                case 2006:
                    
                    // reconnect 5 times
                    $tries = 1;
                    while(true)
                    {
                        // log
                        Toby_Logger::log('MySQL reconnect, attempt '.$tries, 'mysql-queries');
                        
                        // connect
                        if($this->connect() === true) break;
                        if($tries++ >= 5) break;
                        
                        // delay
                        sleep(1);
                    }
                    
                    // repeat query if connected
                    if($this->connected) return $this->query($q);
                    
                    break;
            }
            
            // log & return
            Toby_Logger::log("[MYSQL ERROR] $this->errorCode: $this->errorMessage\nquery: $q", 'mysql-queries');
            return false;
        }

        // fetch result & return
        $this->result = $result;
        return true;
    }
    
    public function select($table, $fields = '*', $appendix = '')
    {
        $query = 'SELECT '.(is_array($fields) ? implode(',', $fields) : (string)$fields).' FROM '.$table.' '.$appendix;
        return $this->query($query);
    }

    public function insert($table, $data)
    {
        $query = "INSERT INTO $table SET {$this->buildDataDefinition($data)}";
        return $this->query($query);
    }

    public function update($table, $data, $appendix = '')
    {
        $query = "UPDATE $table SET {$this->buildDataDefinition($data)} $appendix";
        return $this->query($query);
    }

    public function replace($table, $data)
    {
        $query = "REPLACE INTO $table SET {$this->buildDataDefinition($data)}";
        return $this->query($query);
    }

    public function delete($table, $appendix = '')
    {
        $query = 'DELETE FROM '.$table.' '.$appendix;
        return $this->query($query);
    }
    
    public function remove($table, $appendix = '')
    {
        return $this->delete($table, $appendix);
    }
    
    public function hasTable($tableName)
    {
        $this->query("SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA='$this->db' AND TABLE_NAME='$tableName'");
        if($this->result === false) return false;
        return $this->getNumRows() > 0;
    }
    
    public function hasColumn($tableName, $columnName)
    {
        $this->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$this->db' AND TABLE_NAME='$tableName' AND COLUMN_NAME='$columnName'");
        if($this->result === false) return false;
        return $this->getNumRows() > 0;
    }
    
    public function hasRow($tableName, $appendix = '')
    {
        $this->query("SELECT EXISTS (SELECT 1 FROM $tableName $appendix)");
        if($this->result === false) return false;
        
        return (boolean)$this->fetchFirstElement();
    }
    
    public function countRows($tableName, $appendix = '')
    {
        $this->query("SELECT COUNT(*) FROM $tableName $appendix");
        if($this->result === false) return -1;
        
        return (int)$this->fetchFirstElement();
    }
    
    public function executeQuery(Toby_MySQLQuery $query)
    {
        return $this->query($query->build());
    }
    
    /* supporting methods */
    private function verifyValue($value)
    {
        if($value === null) return 'NULL';
        elseif(is_string($value)) return "'".mysql_real_escape_string($value)."'";
        else return $value;
    }
    
    private function autoEscapeCallback($arr)
    {
        return mysql_real_escape_string($arr[1]);
    }
    
    private function buildDataDefinition($data)
    {
        // init
        $dataDef = '';
        
        // build
        $first = true;
        foreach($data as $key => $value)
        {
            if($first === false) $dataDef.=', ';
            else $first = false;
            
            $dataDef .= "`$key`={$this->verifyValue($value)}";
        }
        
        // return
        return $dataDef;
    }
    
    /* result management */
    public function fetchElementByIndex($index)
    {
        $row = mysql_fetch_row($this->result);
        return $row[$index];
    }
    
    public function fetchFirstElement()
    {
        $row = mysql_fetch_row($this->result);
        return $row[0];
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
        if($this->result === false) return 0;
        return mysql_num_rows($this->result);
    }

    public function getNumAffected()
    {
        return mysql_affected_rows($this->link);
    }
    
    public function getInsertId()
    {
        return mysql_insert_id($this->link);
    }
    
    /* settings */
    public function initQueryLogging()
    {
        $this->logQueries = true;
        Toby_Logger::log('[MySQL log start] '.Toby::getInstance()->request, 'mysql-queries', true);
    }
    
    /* query recording */
    public function startQueryRecording()
    {
        // cancellation
        if($this->queryRec === true) return false;
        
        // start
        $this->queryRec = true;
        $this->queryRecBuffer = array();
        
        // return
        return true;
    }
    
    public function stopQueryRecording()
    {
        // cancellation
        if($this->queryRec === false) return false;
        
        // stop & return
        $this->queryRec = false;
        $out = implode("\n", $this->queryRecBuffer);
        $this->queryRecBuffer = null;
        
        return $out;
    }
    
    /* to string */
    public function __toString()
    {
        return "Toby_MySQL[$this->user@$this->host]";
    }
}