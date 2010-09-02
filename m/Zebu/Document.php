<?php

class Zebu_Document
{
    
    protected $couchDocument;
    protected $isDirty = false;

    public function __construct($arg = null)
    {
    	global $dbcouch;
        if($arg instanceof stdClass){
            $this->couchDocument = $arg;
        } elseif($arg) {
            global $dbcouch;
            $this->couchDocument = $dbcouch->getDoc($arg);
        }else{
        	$this->couchDocument = new Couch_Document($dbcouch);
        	$this->couchDocument->type= static::$type;
        }
        
        if($this->type != $this->couchDocument->type){
            throw new Exception("Invalid Type.");
        }
    }
    
    public function __get($accessor)
    {
        $result = $this->couchDocument->$accessor;
        return $result;
    }
    
    public function __set($accessor, $value)
    {
        $this->isDirty = true;
        $this->couchDocument->$accessor = $value;
    }
    
    public function save()
    {
        global $dbcouch;
        if ($this->isDirty) {
            try {
                $response = $dbcouch->storeDoc($this->couchDocument);
            } catch (Exception $e) {
                echo "ERROR: ".$e->getMessage()." (".$e->getCode().")<br>\n";
            }
        }
    }
    
    public static function createInstance($id = null)
    {
    	$className = static::className();
    	if($id){
        	return new $className($id);
        }
        return new $className();
    }
    
    public static function className()
    {
        return get_called_class();
    }
    
    public static function findAll()
    {
        global $dbcouch, $design;
        
        $results = $dbcouch->getView($design, static::$type);
        $className = static::className();
        
        $objects = array();
        foreach($results->rows as $result){
            array_push($objects, new $className($result->value));
        }
        return $objects;
    }
    
    public static function find($view)
    {
        global $dbcouch,$design;
        
        $results = $dbcouch->getView($design, $view);
        $className = static::className();
        
        $objects = array();
        foreach($results->rows as $result){
            array_push($objects, new $className($result->value));
        }
        return $objects;
    }

}