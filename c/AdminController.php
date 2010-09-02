<?php


class AdminController extends Zend_Controller_Action 
{
    protected $_redirector = null;

    /* helpers */
    public function init(){
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('forgot-password', 'html')
                ->addActionContext('form-forgot-password', 'json')
                ->initContext();
                
        $this->_redirector = $this->_helper->getHelper('Redirector');
        $this->view->user = Zend_Auth::getInstance()->getIdentity();
    }
  
    function preDispatch() 
    { 
        $auth = Zend_Auth::getInstance(); 
        if (!$auth->hasIdentity()) { 
            $this->_redirect('auth/login'); 
        } 
        
    } 
	
    //////////////////////////////////////////////////////////////////////
    // Actions
    //////////////////////////////////////////////////////////////////////

    public function indexAction() 
    {
    
    }

}