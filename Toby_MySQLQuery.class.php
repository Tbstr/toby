<?php


class Toby_MySQLQuery
{
    /* variables */
    private $type;
    
    private $tables         = array();
    private $fields         = array();
    private $data           = array();
    private $conds          = array();
    
    private $orderBy        = null;
    private $limit          = null;
    
    /* constants */
    const TYPE_SELECT       = 'select';
    const TYPE_INSERT       = 'insert';
    const TYPE_UPDATE       = 'update';
    const TYPE_REPLACE      = 'replace';
    const TYPE_DELETE       = 'delete';
    
    /* construct */
    function __construct($type, $tables = null, $fields = null)
    {
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
    
    /* table management */
    public function setTables($tables)
    {
        // cancellation
        if(!is_array($tables)) return $this;
        
        // set
        $this->tables = &$tables;
        
        // return
        return $this;
    }
    
    public function addTable($tableName)
    {
        // add
        $this->tables[] = $tableName;
        
        // return
        return $this;
    }
    
    public function getNumTables()
    {
        return count($this->tables);
    }
    
    /* field management */
    public function setFields($fields)
    {
        // cancellation
        if(!is_array($fields)) return $this;
        
        // set
        $this->fields = &$fields;
        
        // return
        return $this;
    }
    
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
    
    /* data management */
    public function setData($data)
    {
        // cancellation
        if(!is_array($data)) return $this;
        
        // set
        $this->data = &$data;
        
        // return
        return $this;
    }
    
    public function addData($key, $value)
    {
        // add
        $this->data[$key] = $value;
        
        // return
        return $this;
    }
    
    public function getNumData()
    {
        return count($this->data);
    }
    
    /* condition mamagement */
    public function condAND($cond)
    {
        $this->addCond($cond, 'AND');
        
        // return
        return $this;
    }
    
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
    
    public function getNumConditions()
    {
        return count($this->conds);
    }
    
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
        elseif(is_string($value)) return "'".mysql_real_escape_string($value)."'";
        else return $value;
    }
    
    /* build */
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
        return implode(',', $this->tables);
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
        
        // limit
        if($this->limit !== null) $cond .= " LIMIT $this->limit";
        
        // return
        return $cond;
    }
    
    /* to string */
    public function __toString()
    {
        return 'Toby_MySQLQuery';
    }
}