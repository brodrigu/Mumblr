<?php

class Zebu_Map{
    
    protected $map;
    
    public function __construct()
    {
        global $conf;
        $this->map = new Google_Map();
        $this->map->setAPIKey($conf->google->maps->api_key);
        $this->map->setWidth($conf->google->maps->default_width);
        $this->map->setHeight($conf->google->maps->default_height);
        
        $this->map->showControl = $conf->google->maps->show_control;
        
        $this->map->showType = $conf->google->maps->show_type;
        
        $this->map->zoomLevel = $conf->google->maps->zoom_level;
    }
    
    public function __get($accessor)
    {
        return $this->map->$accessor;
    }
    
    public function __set($accessor, $value)
    {
        $this->map->accessor = $value;
    }
    
    public function addAddress($address)
    {
        return $this->map->addAddress($address);
    }
    
    public function showMap()
    {
        return $this->map->showMap();
    }
    
    public function printHeader()
    {
        $this->map->printGoogleJS();
    }
    
    public function addGeoPoint($lat,$long,$infoHTML)
    {
        $this->map->addGeoPoint($lat,$long,$infoHTML);
    }
    
}