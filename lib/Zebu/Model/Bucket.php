<?php

class Zebu_Bucket
{   
    protected $data = array();
    
    public function __construct()
    {
        return $this;
    }
    
    public function __call($functionName, $args)
    {
        $this->data[$functionName] = $args[0];
        return $this;
    }
    
    public function __get($accessor) 
    {
        return $this->data[$accessor];
    }
    
    public function __set($accessor, $value) 
    {
        $this->data[$accessor] = $value;
    }

     public function __isset($accessor) {
        return isset($this->data[$accessor]);
    }

    public function __unset($accessor) {
        unset($this->data[$accessor]);
    }
}