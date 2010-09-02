<?php

interface Zebu_Model_Criteria_Interface
{
    
    public function addCondition($columnName, $operation, $bind);
    public function addSorter($columnName, $direction='ASC');
    public function addGroup($groupBy);
    public function setLimit($limit, $offset=0);
    public function setPage($pageSize, $pageNum=1);
    public function prepare($model);
    
}