<?php

class Toby_MySQL_Result
{
    /**
     * @var mysqli_result
     */
    private $result;

    /**
     * @var Toby_MySQL
     */
    private $parent;

    /**
     * @param mysqli_result $mysqliResult
     * @param Toby_MySQL $parent
     */
    public function __construct($mysqliResult, Toby_MySQL $parent)
    {
        $this->result = $mysqliResult;
        $this->parent = $parent;
    }

    /* result management */
    public function fetchFirstElement()
    {
        $this->checkResult();
        $row = $this->result->fetch_row();
        return $row[0];
    }
    
    public function fetchElementByIndex($index)
    {
        $this->checkResult();
        $row = $this->result->fetch_row();
        return $row[$index];
    }
    
    public function fetchElementSetByIndex($index)
    {
        $this->checkResult();
        $result = array();
        while(($row = $this->result->fetch_row()) !== null) $result[] = $row[$index];
        
        return $result;
    }

    public function fetchElementByName($name)
    {
        $this->checkResult();
        $assoc = $this->result->fetch_assoc();
        return $assoc[$name];
    }
    
    public function fetchElementSetByName($name)
    {
        $this->checkResult();
        $result = array();
        while(($row = $this->result->fetch_assoc()) !== null) $result[] = $row[$name];
        
        return $result;
    }

    public function fetchRow()
    {
        $this->checkResult();
        return $this->result->fetch_row();
    }

    public function fetchRowSet()
    {
        $this->checkResult();

        $entries = array();
        while(($row = $this->result->fetch_row()) !== null) $entries[] = $row;

        return $entries;
    }

    public function fetchAssoc()
    {
        $this->checkResult();
        return $this->result->fetch_assoc();
    }

    public function fetchAssocSet()
    {
        $this->checkResult();

        $entries = array();
        while(($row = $this->result->fetch_assoc()) !== null) $entries[] = $row;

        return $entries;
    }

    public function fetchObject()
    {
        $this->checkResult();
        return $this->result->fetch_object();
    }

    public function fetchObjectSet()
    {
        $this->checkResult();

        $entries = array();
        while(($row = $this->result->fetch_object()) !== null) $entries[] = $row;

        return $entries;
    }

    public function getNumFields()
    {
        $this->checkResult();
        return $this->result->field_count;
    }

    public function getNumRows()
    {
        $this->checkResult();
        return $this->result->num_rows;
    }

    private function checkResult()
    {
        if ($this->result === null)
        {
            throw new Toby_MySQL_Exception("using freed result");
        }
    }

    public function freeResult()
    {
        if ($this->result !== null)
        {
            $this->result->free();
            $this->result = null;
            $this->parent->releaseResult($this);
        }
        else
        {
            throw new Toby_MySQL_Exception("double free mysql result");
        }
    }

    public function __destruct()
    {
        if ($this->result !== null)
        {
            $this->freeResult();
        }
    }
}