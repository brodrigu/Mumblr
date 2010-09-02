<?php

  /* USAGE *
  
  operators: see constants defined below
  
  $crit = new Zebu_Model_Criteria();
  $crit->addCondition( $colname, $op, $bind);
  $crit->addSorter( $colname, $dir, $pos);
  
  // this
  $crit->limit = 10;
  $crit->offset = 30;
  
  // OR
  $crit->page = 3;
  $crit->pageSize = 20;

  */

class Zebu_Model_Criteria implements Zebu_Model_Criteria_Interface
{
    // TODO: store offset/limit, pageNum/pageSize as separate vars,
    // provide public getters
    protected $where = array();
    protected $sort  = array();
    protected $groupBy = array();
    protected $offset;
    protected $limit;
    protected $pageNum;
    protected $pageSize;

    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';

    const OP_GT = '>';
    const OP_GTE = '>=';
    const OP_LT = '<';
    const OP_LTE = '<=';
    const OP_EQ = '=';
    const OP_NE = '!=';
    const OP_LIKE = 'LIKE';
    const OP_NOT_LIKE = 'NOT LIKE';
    const OP_IN = 'IN';
    const OP_NOT_IN = 'NOT IN';



    public function __construct ()
    {

    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        return false;
    }

    public function __isset($name) {
        $value = $this->$name;
        return isset($value);
    }

    public function addCondition($columnName, $operation, $bind)
    {
        // TODO: make sure data is clean by using bind from the caller
        if(in_array($operation,array(static::OP_GT, 
                                     static::OP_GTE,
                                     static::OP_LT, 
                                     static::OP_LTE,
                                     static::OP_EQ,
                                     static::OP_NE, 
                                     static::OP_LIKE, 
                                     static::OP_NOT_LIKE))){
            array_push($this->where, array('column_name' => $columnName, 'operation' => $operation, 'bind' => $bind));

        } elseif (in_array($operation,array(static::OP_IN, 
                                            static::OP_NOT_IN)))	{
            array_push($this->where, array('column_name' => $columnName, 'operation' => $operation, 'bind' => $bind));

        } else {
            throw new Zebu_Model_Exception("Unexpected operator: $operation");
        }
    }

    public function addSorter($columnName, $direction='ASC')
    {
        if(in_array($direction,array(static::SORT_ASC, static::SORT_DESC))){
            array_push($this->sort,"$columnName $direction");
        } else {
            throw new Zebu_Model_Exception("Unexpected direction: $direction");
        }
    }

    //write a test for this with sort
    public function addGroup($groupBy)
    {
        array_push($this->groupBy,$groupBy);
    }

    public function setLimit($limit, $offset=0)
    {
        if(!empty($this->pageNum) || !empty($this->pageSize)){
            throw new Zebu_Model_Exception("Cannot set limit, pagination already set.");
        }
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function setPage($pageSize, $pageNum=1)
    {
        if(!empty($this->limit)){
            throw new Zebu_Model_Exception("Cannot set pagination, limit already set.");
        }
        $this->pageSize = $pageSize;
        $this->pageNum = $pageNum;
    }
	

    public function prepare($model)
    {
        //this criteria object does not need to do any preparation
    }
}

