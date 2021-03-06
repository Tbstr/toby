<?php

namespace Toby\MySQL;

use Toby\Config;
use Toby\Toby;
use Toby\Utils\SysUtils;

class MySQL
{
    /** @var MySQL[] */
    private static $instances   = array();

    /** @var \mysqli */
    private $mysqli             = null;
    
    private $host;
    private $user;
    private $pass;
    private $db;

    /** @var MySQLResult */
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

    private $numTransactions    = 0;

    /** @var \Logger */
    private $logger;

    /** @var \Logger */
    private $queryLogger;

    private $performanceData;

    /**
     * @param string $id
     * @param bool $autoInit
     *
     * @return MySQL
     */
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

    public function __construct()
    {
        $this->logger = \Logger::getLogger("toby.mysql");
        $this->queryLogger = \Logger::getLogger("toby.mysql.queries");
    }
    
    /* init & connect */
    protected function autoInit()
    {
        // cancellation
        if(Config::get('toby.mysql.auto_connect') !== true) return;
        
        // vars
        $this->init(
            Config::get('toby.mysql.host'),
            Config::get('toby.mysql.user'),
            Config::get('toby.mysql.password'),
            Config::get('toby.mysql.db')
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
        if(Config::get('toby.logging.log_mysql_queries')) $this->initQueryLogging();
        
        // connect
        if($this->connected) $this->disconnect();
        return $this->connect();
    }

    public function connect()
    {
        // reset connected state
        $this->connected = false;
        
        // connect
        if($this->db === false) $this->mysqli = new \mysqli($this->host, $this->user, $this->pass);
        else                    $this->mysqli = new \mysqli($this->host, $this->user, $this->pass, $this->db);
        
        if($this->mysqli->connect_errno !== 0)
        {
            if($this->throwExceptions) throw new MySQLException($this->mysqli->connect_errno.': connection failed: '.$this->mysqli->connect_error, $this->mysqli->connect_errno);

            $this->logger->error('mysql connection failed ('.$this->mysqli->connect_errno.': '.$this->mysqli->connect_error.')');
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
        return $this->mysqli->ping();
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
        if($this->logQueries) $this->queryLogger->info(str_replace(array("\n", "\r"), '', $q));

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
            SysUtils::printr($q);
            return true;
        }

        $queryStart = microtime(true);

        // query
        $result = $this->mysqli->query($q);

        if ($this->isPerformanceTrackingEnabled())
        {
            $stop = microtime(true);
            $this->addPerformanceData($q, $stop - $queryStart);
        }

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

                    if($this->numTransactions === 0)
                    {
                        // reconnect 5 times
                        $tries = 1;
                        while (true)
                        {
                            // log
                            $this->queryLogger->info('MySQL reconnect, attempt '.$tries, 'mysql-queries');

                            // connect
                            if($this->connect() === true) break;
                            if($tries++ >= 5) break;

                            // delay
                            sleep(1);
                        }

                        // repeat query if connected
                        if($this->connected) return $this->query($q);
                    }

                    break;
            }

            // throw error
            if($this->throwExceptions) throw new MySQLException("$this->errorCode: $this->errorMessage :: QUERY: $q", $this->errorCode);

            // log & return
            $this->logger->error("[MYSQL ERROR] $this->errorCode: $this->errorMessage\nquery: $q");
            return false;
        }
        elseif($result === true)
        {
            // no select query
            $this->result = true;
            return true;
        }

        // fetch result & return
        $this->result = new MySQLResult($result, $this);
        return $this->result;
    }
    
    /**
     * prepares a query
     *
     * @param $query
     * @return bool|MySQLStatement
     * @throws MySQLException
     */
    public function prepare($query)
    {
        $stmt = $this->mysqli->prepare($query);
        if($stmt === false)
        {
            $this->errorMessage     = $this->mysqli->error;
            $this->errorCode        = $this->mysqli->errno;

            // throw exception
            if($this->throwExceptions) throw new MySQLException("$this->errorCode: $this->errorMessage", $this->errorCode);

            // error & return
            $this->logger->error("statement preparation failed ($this->errorCode: $this->errorMessage)");
            return false;
        }

        return new MySQLStatement($stmt, $this, $query);
    }

    /* transactions */
    public function beginTransaction()
    {
        if($this->numTransactions === 0)
        {
            if(!$this->query('START TRANSACTION'))
            {
                // throw exception
                if($this->throwExceptions) throw new MySQLException("$this->errorCode: starting transaction failed", $this->errorCode);

                // error & return
                $this->logger->error("$this->errorCode: starting transaction failed");
                return false;
            }
        }

        $this->numTransactions++;

        // return
        return true;
    }
    
    public function endTransaction($commit = true)
    {
        // cancellation
        if($commit === true && $this->numTransactions > 1)
        {
            $this->numTransactions--;
            return true;
        }

        // apply & return
        if($commit === true)    $success = $this->query('COMMIT');
        else                    $success = $this->query('ROLLBACK');

        // error handling
        if($success === false)
        {
            // throw exception
            if($this->throwExceptions) throw new MySQLException("$this->errorCode: ".($commit ? 'commit' : 'rollback').' failed', $this->errorCode);

            // error & return
            $this->logger->error('[MYSQL ERROR] '.($commit ? 'commit' : 'rollback').' failed');
            return false;
        }

        // reset num transactions
        $this->numTransactions = 0;

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
        $r = $this->query("SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA='$this->db' AND TABLE_NAME='$tableName'");
        if($r === false) return false;
        return $r->getNumRows() > 0;
    }
    
    public function hasColumn($tableName, $columnName)
    {
        $r = $this->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$this->db' AND TABLE_NAME='$tableName' AND COLUMN_NAME='$columnName'");
        if($r === false) return false;
        return $r->getNumRows() > 0;
    }
    
    public function hasRow($tableName, $appendix = '')
    {
        $r = $this->query("SELECT EXISTS (SELECT 1 FROM $tableName $appendix)");
        if($r === false) return false;
        
        return (boolean)$r->fetchFirstElement();
    }
    
    public function countRows($tableName, $appendix = '')
    {
        $r = $this->query("SELECT COUNT(*) FROM $tableName $appendix");
        if($r === false) return -1;
        
        return (int)$r->fetchFirstElement();
    }
    
    /* query supporting methods */
    public function esc($string)
    {
        return $this->mysqli->real_escape_string($string);
    }
    
    private function verifyValue($value)
    {
        if($value === null)                     return 'NULL';
        elseif(is_string($value))               return "'".$this->mysqli->real_escape_string($value)."'";
        elseif($value instanceof \DateTime)      return "'".$value->format('Y-m-d H:i:s')."'";
        elseif($value instanceof Decimal)       return number_format($value->getValue(), 5, '.', '');
        else                                    return $value;
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
        if ($this->hasResult())
        {
            $this->result->freeResult();
        }
    }
    
    public function releaseResult(MySQLResult $result)
    {
        if($result === $this->result)
        {
            $this->result = false;
        }
    }
    
    /* settings */
    public function initQueryLogging()
    {
        $this->logQueries = true;
        $this->queryLogger->info('[MySQL log start] '.Toby::getInstance()->request);
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

    private function hasResult($throwException = false)
    {
        if($this->result === false || $this->result === true)
        {
            // result is true if last query was a data modification (UPDATE, INSERT or DELETE)
            if($throwException)     throw new MySQLException("no current result");
            else                    return false;
        }
        else
        {
            return true;
        }
    }
    
    /* to string */
    public function __toString()
    {
        return "MySQL[$this->user@$this->host]";
    }

    public function startPerformanceTracking()
    {
        $this->performanceData = array();
    }

    public function getPerformanceData()
    {
        return $this->performanceData;
    }

    public function stopPerformanceTracking()
    {
        $data = $this->performanceData;
        $this->performanceData = null;
        return $data;
    }

    public function isPerformanceTrackingEnabled()
    {
        return $this->performanceData !== null;
    }

    public function addPerformanceData($query, $duration)
    {
        $this->performanceData[] = [$query, $duration * 1000];
    }
}
