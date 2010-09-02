<?php 
class AuthController extends Zend_Controller_Action  
{ 
    function init() 
    { 
        $this->initView(); 
        $this->view->baseUrl = $this->_request->getBaseUrl(); 
    }
         
    function indexAction() 
    { 
        $this->_redirect('admin/'); 
    }

    function loginAction() 
    { 
        $this->view->message = ''; 
        if ($this->_request->isPost()) { 
            // collect the data from the user 
            //Zend_Loader::loadClass('Zend_Filter_StripTags'); 
            $f = new Zend_Filter_StripTags(); 
            $username = $f->filter($this->_request->getPost('username')); 
            $password = md5($f->filter($this->_request->getPost('password'))); 
         
            if (empty($username)) { 
                $this->view->message = 'Please provide a username.'; 
            } else { 
                // setup Zend_Auth adapter for a database table 
                //Zend_Loader::loadClass('Zend_Auth_Adapter_DbTable'); 
                //global $db; 
                //$authAdapter = new Zend_Auth_Adapter_DbTable($db); 
                //$authAdapter->setTableName('users'); 
                //$authAdapter->setIdentityColumn('username'); 
                //$authAdapter->setCredentialColumn('passhash'); 
                 
                // Set the input credential values to authenticate against 
                //$authAdapter->setIdentity($username); 
                //$authAdapter->setCredential($password); 
                 
                 $authAdapter = new Zebu_Auth_Adapter($username, $password);
                // do the authentication  
                $auth = Zend_Auth::getInstance(); 
                $result = $auth->authenticate($authAdapter);
                 
                if ($result->isValid()) { 
                    // success: store database row to auth's storage 
                    // system. (Not the password though!) 
                    $data = $authAdapter->getUserObject(); 
                    $auth->getStorage()->write($data); 
                    $this->_redirect('admin/'); 
                } else { 
                    // failure: clear database row from session 
                    $this->view->message = 'Login failed.'; 
                } 
            } 
        } 
        $this->view->title = "Log in"; 
    }
    
    function logoutAction() 
    { 
        Zend_Auth::getInstance()->clearIdentity(); 
        $this->_redirect('/'); 
    }
} 
