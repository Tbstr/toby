<?php

class Toby_MySQLi
{
    private static $instances   = array();

    private $mysqli             = null;
    
    private $host;
    private $user;
    private $pass;
    private $db;
    
    public $result              = false;
    
    public $errorMessage        = '';
    public $errorCode           = '';

    public $mysqlCharset        = 'utf8';
    public $dryRun              = false;
    public $logQueries          = false;
    public $throwExceptions     = false;

    public $connected           = false;

    private $queryRec           = false;
    private $queryRecBuffer;

    public static function getInstance($id = null, $autoInit = true)
    {
        if(empty($id)) $id = 'default';

        if(!isset(self::$instances[$id]))
        {
            self::$instances[$id] = new self();
            if($id === 'default' && $autoInit === true) self::$instances[$id]->autoInit();
        }

        return self::$instances[$id];
    }
    
    /* init & connect */
    private function autoInit()
    {
        // cancellation
        if(Toby_Config::get('toby')->getValue('mySQLAutoConnect') !== true) return;
        
        // vars
        $mad = Toby_Config::get('toby')->getValue('mySQLAccessData');

        $this->init(
            $mad['host'],
            $mad['user'],
            $mad['password'],
            isset($mad['db']) ? $mad['db'] : null
            );
    }

    public function init($host, $user, $pass, $db = false)
    {
        // vars
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        if($db !== false) $this->db = $db;
        
        // setup
        if(Toby_Config::get('toby')->getValue('logMySQLQueries')) $this->initQueryLogging();
        
        // connect
        if($this->connected) $this->disconnect();
        return $this->connect();
    }

    public function connect()
    {
        // reset connected state
        $this->connected = false;
        
        // connect
        if($this->db === false) $this->mysqli = new mysqli($this->host, $this->user, $this->pass);
        else                    $this->mysqli = new mysqli($this->host, $this->user, $this->pass, $this->db);
        
        if($this->mysqli->connect_errno !== 0)
        {
            if($this->throwExceptions) throw new Exception('mysql connection failed ('.$this->mysqli->connect_errno.': '.$this->mysqli->connect_error.')');

            Toby_Logger::error('mysql connection failed ('.$this->mysqli->connect_errno.': '.$this->mysqli->connect_error.')');
            return false;
        }

        // set charset
        $this->mysqli->set_charset($this->mysqlCharset);

        // set & return
        $this->connected = true;
        return true;
    }

    public function disconnect()
    {
        if($this->mysqli !== null) $this->mysqli->close();
        $this->mysqli = null;
        
        $this->connected = false;
    }
    
    public function ping()
    {
        if(!$this->connected) return false;
        return $this->mysqli->ping;
    }
    
    public function stat()
    {
        return $this->mysqli->stat();
    }
    
    public function selectDatabase($dbName)
    {
        return $this->mysqli->select_db($dbName);
    }
    
    /* low level query methods */
    private function initQuery()
    {
        // reset
        $this->result       = false;
        
        $this->errorCode    = 0;
        $this->errorMessage = '';
    }
    
    public function query($q)
    {
        // init
        $this->initQuery();

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
        
        // query
        $result = $this->mysqli->query($q);
        
        // handle error
        if($result === false)
        {
            // set error vars
            $this->errorMessage     = $this->mysqli->error;
            $this->errorCode        = $this->mysqli->errno;
            
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

            // throw error
            if($this->throwExceptions) throw new Exception("[MYSQL ERROR] $this->errorCode: $this->errorMessage\nquery: $q");

            // log & return
            Toby_Logger::error("[MYSQL ERROR] $this->errorCode: $this->errorMessage\nquery: $q", 'mysql-queries');
            return false;
        }

        // fetch result & return
        $this->result = $result;
        return true;
    }
    
    public function queryPrepared($q, $paramTypes, $paramSet)
    {
        // cancellation
        if(empty($paramSet)) return false;
        
        // init
        $this->initQuery();
        
        // prepare statement
        $stmt = $this->mysqli->prepare($q);
        if($stmt === false)
        {
            $this->errorMessage     = $this->mysqli->error;
            $this->errorCode        = $this->mysqli->errno;

            // throw exception
            if($this->throwExceptions) throw new Exception("statement preparation failed ($this->errorCode: $this->errorMessage)");

            // error & return
            Toby_Logger::error("statement preparation failed ($this->errorCode: $this->errorMessage)");
            return false;
        }
        
        // bind param sets & execute
        $keyCount = count($paramSet[0]);
        $bindArr = array($paramTypes);
        
        foreach($paramSet as $params)
        {
            // prepare params
            for($i = 0; $i < $keyCount; $i++) $bindArr[$i+1] = &$params[$i];
            
            // bind
            if(call_user_func_array(array($stmt, 'bind_param'), $bindArr) === false)
            {
                $this->errorMessage     = $this->mysqli->error;
                $this->errorCode        = $this->mysqli->errno;

                // throw exception
                if($this->throwExceptions) throw new Exception("param bind failed ($this->errorCode: $this->errorMessage)");

                // error & return
                Toby_Logger::error("param bind failed ($this->errorCode: $this->errorMessage)");
                return false;
            }
            
            // execute
            if($stmt->execute() === false)
            {
                $this->errorMessage     = $this->mysqli->error;
                $this->errorCode        = $this->mysqli->errno;

                // throw exception
                if($this->throwExceptions) throw new Exception("statement execution failed ($this->errorCode: $this->errorMessage)");

                // error & return
                Toby_Logger::error("statement execution failed ($this->errorCode: $this->errorMessage)");
                return false;
            }
        }
        
        // close statement
        $stmt->close();
        
        // return success
        return true;
    }

    /* transactions */
    public function beginTransaction()
    {
        return $this->query('START TRANSACTION');
    }
    
    public function endTransaction($commit = true)
    {
        // vars
        $success = false;

        // apply & return
        if($commit === true)    $success = $this->query('COMMIT');
        else                    $success = $this->query('ROLLBACK');

        // error handling
        if($success === false)
        {
            // throw exception
            if($this->throwExceptions) throw new Exception('[MYSQL ERROR] '.($commit ? 'commit' : 'rollback').' failed');

            // error & return
            Toby_Logger::error('[MYSQL ERROR] '.($commit ? 'commit' : 'rollback').' failed');
        }

        // return success
        return true;
    }
    
    /* high level query methods */
    public function select($table, $fields = '*', $appendix = '')
    {
        $query = 'SELECT '.(is_array($fields) ? implode(',', $fields) : (string)$fields).' FROM '.$table.' '.$appendix;
        return $this->query($query);
    }

    public function insert($table, $data, $onDuplicateKeyData = false)
    {
        // cancellation
        if(empty($table)) return false;
        if(empty($data)) return false;
        
        // detect query type
        $single = false;
        foreach($data as $value)
        {
            if(!is_array($value))
            {
                $single = true;
                break;
            }
        }
        
        // single insert
        if($single)
        {
            $query = "INSERT INTO $table SET {$this->buildDataDefinition($data)}";
        }
        
        // multi insert
        else
        {
            // vars
            $keys       = array_keys($data);
            
            $keyCount   = count($keys);
            if($keyCount === 0) return false;
            
            $dataCount  = count($data[$keys[0]]);
            if($dataCount === 0) return false;
            
            $values = array();
            $valueElements = null;
            
            for($i = 0; $i < $dataCount; $i++)
            {
                $valueElements = array();
                for($j = 0; $j < $keyCount; $j++) $valueElements[] = $data[$keys[$j]][$i];
                
                $values[] = $this->buildValueSet($valueElements);
            }
            
            foreach($keys as $key => $value) $keys[$key] = '`'.$value.'`';
            $query = 'INSERT INTO '.$table.' ('.implode(',',$keys).') VALUES '.implode(',',$values);
        }

        // on duplicate key
        if(is_array($onDuplicateKeyData)) $query .= " ON DUPLICATE KEY UPDATE {$this->buildDataDefinition($onDuplicateKeyData)}";
        
        // query & return;
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
    
    /* query supporting methods */
    public function esc($string)
    {
        return $this->mysqli->real_escape_string($string);
    }
    
    private function verifyValue($value)
    {
        if($value === null)         return 'NULL';
        elseif(is_string($value))   return "'".$this->mysqli->real_escape_string($value)."'";
        else                        return $value;
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
    
    private function buildValueSet($values)
    {
        // build
        $valueEnries = array();
        foreach($values as $value) $valueEnries[] = $this->verifyValue($value);
        
        // return
        return '('.implode(',', $valueEnries).')';
    }
    
    /* result management */
    public function fetchFirstElement()
    {
        $row = $this->result->fetch_row();
        return $row[0];
    }
    
    public function fetchElementByIndex($index)
    {
        $row = $this->result->fetch_row();
        return $row[$index];
    }
    
    public function fetchElementSetByIndex($index)
    {
        $result = array();
        while(($row = $this->result->fetch_row()) !== null) $result[] = $row[$index];
        
        return $result;
    }

    public function fetchElementByName($name)
    {
        $assoc = $this->result->fetch_assoc();
        return $assoc[$name];
    }
    
    public function fetchElementSetByName($name)
    {
        $result = array();
        while(($row = $this->result->fetch_assoc()) !== null) $result[] = $row[$name];
        
        return $result;
    }

    public function fetchRow()
    {
        if($this->result === false) return null;
        return $this->result->fetch_row();
    }

    public function fetchRowSet()
    {
        if($this->result === false) return array();
        
        $entries = array();
        while(($row = $this->result->fetch_row()) !== null) $entries[] = $row;

        return $entries;
    }

    public function fetchAssoc()
    {
        if($this->result === false) return null;
        return $this->result->fetch_assoc();
    }

    public function fetchAssocSet()
    {
        if($this->result === false) return array();
        
        $entries = array();
        while(($row = $this->result->fetch_assoc()) !== null) $entries[] = $row;

        return $entries;
    }

    public function fetchObject()
    {
        if($this->result === false) return null;
        return $this->mysqli->fetch_object();
    }

    public function fetchObjectSet()
    {
        if($this->result === false) return array();
        
        $entries = array();
        while(($row = $this->result->fetch_object()) !== null) $entries[] = $row;

        return $entries;
    }
    
    public function getNumFields()
    {
        return $this->mysqli->field_count;
    }
    
    public function getNumRows()
    {
        if($this->result === false) return 0;
        return $this->result->num_rows;
    }

    public function getNumAffected()
    {
        return $this->mysqli->affected_rows;
    }
    
    public function getInsertId()
    {
        return $this->mysqli->insert_id;
    }
    
    public function freeResult()
    {
        if($this->result !== false)
        {
            $this->result->free();
            $this->result = false;
        }
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
        if($this->queryRec === true) return true;
        
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
        return "Toby_MySQLi[$this->user@$this->host]";
    }
}