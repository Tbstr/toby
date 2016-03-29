<?php

namespace Toby\MySQL;

class MySQLQuery
{
    /* variables */

    /** @var MySQL */
    public $mysql;
    
    private $type;
    
    private $tables         = array();
    private $joins          = array();

    private $fields         = array();
    private $data           = array();

    private $conds          = array();
    
    private $orderBy        = null;
    private $groupBy        = null;
    private $limit          = null;
    
    /* constants */
    const TYPE_SELECT       = 'select';
    const TYPE_INSERT       = 'insert';
    const TYPE_UPDATE       = 'update';
    const TYPE_REPLACE      = 'replace';
    const TYPE_DELETE       = 'delete';

    const JOIN_INNER        = 'inner';
    const JOIN_LEFT         = 'left';
    
    /* construct */
    function __construct($mysql, $type, $tables = null, $fields = null)
    {
        // mysql
        $this->mysql = $mysql;
        
        // type
        $this->type = $type;
        
        // tables
        if($tables !== null)
        {
            if(!is_array($tables)) $tables = explode(',', $tables);
            $this->tables = array_merge($this->tables, $tables);
        }
        
        // fields
        if($fields !== null)
        {
            if(!is_array($fields)) $fields = explode(',', $fields);
            $this->fields = array_merge($this->fields, $fields);
        }
    }
    
    /* TABLE MANAGEMENT */

    /**
     * @param array $tables
     *
     * @return MySQLQuery
     */
    public function setTables(array $tables)
    {
        // cancellation
        if(!is_array($tables)) return $this;
        
        // set
        $this->tables = &$tables;
        
        // return
        return $this;
    }

    /**
     * @param string $tableName
     *
     * @return MySQLQuery
     */
    public function addTable($tableName)
    {
        // add
        $this->tables[] = $tableName;
        
        // return
        return $this;
    }

    /**
     * @param string $table
     * @param string $on
     * @param string $joinType
     *
     * @return MySQLQuery
     */
    public function join($table, $on, $joinType = 'inner')
    {
        // add
        $this->joins[] = array($table, $on, $joinType);

        // return
        return $this;
    }

    /**
     * @return int
     */
    public function getNumTables()
    {
        return count($this->tables);
    }
    
    /* FIELD MANAGEMENT */

    /**
     * @param array $fields
     *
     * @return MySQLQuery
     */
    public function setFields(array $fields)
    {
        // cancellation
        if(!is_array($fields)) return $this;
        
        // set
        $this->fields = &$fields;
        
        // return
        return $this;
    }

    /**
     * @param string $fieldName
     *
     * @return MySQLQuery
     */
    public function addField($fieldName)
    {
        // add
        $this->fields[] = $fieldName;
        
        // return
        return $this;
    }
    
    public function getNumFields()
    {
        return count($this->fields);
    }
    
    /* DATA MANAGEMENT */

    /**
     * @param array $data
     *
     * @return MySQLQuery
     */
    public function setData(array $data)
    {
        // cancellation
        if(!is_array($data)) return $this;
        
        // set
        $this->data = &$data;
        
        // return
        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return MySQLQuery
     */
    public function addData($key, $value)
    {
        // add
        $this->data[$key] = $value;
        
        // return
        return $this;
    }

    /**
     * @return int
     */
    public function getNumData()
    {
        return count($this->data);
    }
    
    /* CONDITION MANAGEMENT */

    /**
     * @param string $cond
     *
     * @return MySQLQuery
     */
    public function condAND($cond)
    {
        $this->addCond($cond, 'AND');
        
        // return
        return $this;
    }

    /**
     * @param string $cond
     *
     * @return MySQLQuery
     */
    public function condOR($cond)
    {
        $this->addCond($cond, 'OR');
        
        // return
        return $this;
    }
    
    private function addCond($cond, $tie = 'AND')
    {
        $this->conds[] = (empty($this->conds) ? '' : $tie.' ') . $cond;
    }

    /**
     * @return int
     */
    public function getNumConditions()
    {
        return count($this->conds);
    }

    /**
     * @param string $fieldName
     * @param bool   $asc
     *
     * @return MySQLQuery
     */
    public function orderBy($fieldName, $asc = false)
    {
        // cancellate
        if(!is_string($fieldName))
        {
            $this->orderBy = null;
            return $this;
        }
        
        // set
        $this->orderBy = "$fieldName ".($asc ? 'ASC' : 'DESC');
        
        // return
        return $this;
    }

    /**
     * @param string $fieldName
     *
     * @return MySQLQuery
     */
    public function groupBy($fieldName)
    {
        // cancellate
        if(empty($fieldName)) return $this;
        
        // set
        $this->groupBy = $fieldName;
        
        // return
        return $this;
    }

    /**
     * @param int $amount
     * @param int $startIndex
     *
     * @return MySQLQuery
     */
    public function limit($amount, $startIndex = 0)
    {
        // cancellate
        if(!is_numeric($amount) || $amount < 0)
        {
            $this->limit = null;
            return $this;
        }
        
        // set
        $this->limit = ($startIndex === 0 ? $amount : "$startIndex, $amount");
        
        // return
        return $this;
    }
    
    /* methods */
    private function verifyValue($value)
    {
        if($value === null) return 'NULL';
        elseif(is_string($value)) return "'".$this->mysql->esc($value)."'";
        else return $value;
    }
    
    /* BUILD */

    /**
     * @return string
     * @throws MySQLException
     */
    public function build()
    {
        // init
        $q = '';
        
        // type
        switch($this->type)
        {
            case self::TYPE_SELECT:
                $q = "SELECT {$this->buildFields()} FROM {$this->buildTables()} {$this->buildConditions()}";
                break;
            
            case self::TYPE_INSERT:
                $q = "INSERT INTO {$this->buildTables()} SET {$this->buildDataDefinition()}";
                break;
            
            case self::TYPE_UPDATE:
               $q = "UPDATE {$this->buildTables()} SET {$this->buildDataDefinition()} {$this->buildConditions()}";
                break;
            
            case self::TYPE_REPLACE:
                $q = "REPLACE INTO {$this->buildTables()} SET {$this->buildDataDefinition()}";
                break;
            
            case self::TYPE_DELETE:
                $q = "DELETE FROM {$this->buildTables()} {$this->buildConditions()}";
                break;
        }
        
        // return
        return $q;
    }
    
    private function buildTables()
    {
        // set tables
        $out = implode(',', $this->tables);

        // add joins
        foreach($this->joins as $join)
        {
            switch($join[2])
            {
                case self::JOIN_INNER:
                    $out .= ' INNER JOIN ';
                    break;

                case self::JOIN_LEFT:
                    $out .= ' LEFT JOIN ';
                    break;

                default:
                    throw new MySQLException('invalid join type given');
            }

            $out .= $join[0].' ON '.$join[1];
        }

        // return
        return $out;
    }
    
    private function buildFields()
    {
        if(empty($this->fields)) return '*';
        return implode(',', $this->fields);
    }
    
    private function buildDataDefinition()
    {
        // init
        $dataDef = '';
        
        // build
        $first = true;
        foreach($this->data as $key => $value)
        {
            if($first === false) $dataDef.=', ';
            else $first = false;
            
            $dataDef .= "`$key`={$this->verifyValue($value)}";
        }
        
        // return
        return $dataDef;
    }
    
    private function buildConditions()
    {
        // init
        $cond = '';
        
        // conditions
        if(!empty($this->conds))
        {
            $cond .= 'WHERE ' . implode(' ', $this->conds);
        }
        
        // order
        if($this->orderBy !== null) $cond .= " ORDER BY $this->orderBy";
        
        // group
        if($this->groupBy !== null) $cond .= " GROUP BY $this->groupBy";
        
        // limit
        if($this->limit !== null) $cond .= " LIMIT $this->limit";
        
        // return
        return $cond;
    }
    
    /* EXECUTION */

    /**
     * @return bool
     */
    public function execute()
    {
        return $this->mysql->query($this->build());
    }
    
    /* to string */
    public function __toString()
    {
        return 'Toby_MySQL_Query';
    }
}