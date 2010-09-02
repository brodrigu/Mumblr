<?php

//////////////////////////////////////////////////////////////////////
// Auth Interface via couchDB
//
class Zebu_Auth_Adapter implements Zend_Auth_Adapter_Interface
{
    protected $username;
    protected $passhash;
    
    public function __construct( $username, $password) {
        global $conf;
        
        $salt = $conf->auth->salt;
        $this->username = $username;
        $this->passhash = md5($salt.$password);
        
    }

    //////////////////////////////////////////////////////////////////////
    // authenticate a user utilizing the auth library
    public function authenticate() {
        $user = Zebu_User::createInstance($this->username);
        if($user && $this->passhash == $user->passhash){
            return new Zend_Auth_Result(Zend_Auth_Result::SUCCESS,$this->username);
        }
        return new Zend_Auth_Result(Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,$this->username);
    }
    
    public function getUserObject()
    {
        return Zebu_User::createInstance($this->username);
    }
}