<?php

class ApplicationBootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initView()
    {
        // Initialize view
        $view = new Zend_View();
        $view->doctype('XHTML1_STRICT');
        $view->headTitle('Mumblr');
        $view->setScriptPath(APPLICATION_PATH . '/v');
        // Add it to the ViewRenderer
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper(
            'ViewRenderer'
        );
        
        $viewRenderer->setView($view);
        $viewRenderer->setViewSuffix('tpl');
 
        // Return it, so that it can be stored by the bootstrap
        return $view;
    }
}