<?php

class ApplicationBootstrap
{
    public static function strap()
    {
        static::setupEnvironment();
        static::setApplicationConstants();
        static::setupAutoloader();
        static::setupConfigurationObject();
        static::setupDatabase();
        static::setupRegistry();
        static::setupLogging();
        static::setupNamespaces();
        static::setupConvenienceGlobals();
        static::setupFrontController();
        static::setupView();
        static::setupExceptionHandling();
        static::setErrorReporting();
    }

    public static function setupEnvironment()
    {
        date_default_timezone_set('America/Los_Angeles');
    }

    public static function setApplicationConstants()
    {  
        global $env;
        
        // APPLICATION CONSTANTS - Set the constants to use in this
        // application. These constants are accessible throughout
        // the application, even in ini files. We optionally set
        // APPLICATION_PATH here in case our entry point isn't
        // index.php (e.g., if required from our test suite or a
        // script).
        //define('APPLICATION_PATH', dirname(__FILE__));

        // auto-detect unix home dir, go into dev env
        /*
        preg_match('/^\/home\/([^\/]+)\//', APPLICATION_PATH, $matches);
        if ( $matches[1] ) {
            $env = $matches[1];
        } else {
            $env = 'dev';
        }
        */
        
        $env = 'dev';
        
        define('APPLICATION_ENVIRONMENT', $env);
        unset( $env, $matches );

    }
    
    public static function setupAutoloader()
    {
        require_once "Zend/Loader/Autoloader.php";
        global $autoloader;
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }

    public static function setupConfigurationObject()
    {
        $GLOBALS['conf'] =
            new Zend_Config_Ini( APPLICATION_PATH . '/conf/app.ini',
                                 APPLICATION_ENVIRONMENT,
                                 array('allowModifications' => true));
    }

    public static function setupDatabase()
    {

        // setup a primary database
        $GLOBALS['db_primary'] =
            Zend_Db::factory($GLOBALS['conf']->db->primary);
        $GLOBALS['db_couch'] = new Couch_Client($GLOBALS['conf']->db->couch->params->host, $GLOBALS['conf']->db->couch->params->port, $GLOBALS['conf']->db->couch->params->dbname);
    }
    
    public static function setupRegistry()
    {
        $GLOBALS['reg'] = Zend_Registry::getInstance();
    }

    public static function setupLogging()
    {
        $GLOBALS['log'] = new Zend_Log();
        // TODO: update this to read a formatter that does usefule things
        if ( $GLOBALS['conf']->log->file ) {
            $GLOBALS['log']->addWriter(new Zend_Log_Writer_Stream( $GLOBALS['conf']->log->file ));
        }
        if ( $GLOBALS['conf']->log->firephp ) {
            $GLOBALS['log']->addWriter(new Zend_Log_Writer_Firebug());
        }
    }

    
    public static function setupNamespaces()
    {
        $GLOBALS['namespaces'] = array('Application_');
    }

    public static function setupConvenienceGlobals()
    {
        global $log, $reg, $conf, $user, $namespaces;
        // Convenience globals
        $log  = $GLOBALS['log'];
        $reg  = $GLOBALS['reg'];
        $conf = $GLOBALS['conf'];
        $namespaces = $GLOBALS['namespaces'];

        // we always want the db to be convenient
        global $db,$dbcouch, $design;
        $db      = $GLOBALS['db_primary'];
        $dbcouch =  $GLOBALS['db_couch'];
        $design = $GLOBALS['conf']->db->couch->design;

       // this filled in by AccessPlugin on login
        $user = '';
    }
        
    public static function setupFrontController()
    {
        global $frontController;

        // FRONT CONTROLLER - Get the front controller. The
        // Zend_Front_Controller class implements the Singleton
        // pattern, which is a design pattern used to ensure there is
        // only one instance of Zend_Front_Controller created on each
        // request.
        $frontController = Zend_Controller_Front::getInstance();

        // CONTROLLER DIRECTORY SETUP - Point the front controller to
        // your action controller directory.

        $frontController->setControllerDirectory(APPLICATION_PATH . '/c');
                
        // APPLICATION ENVIRONMENT - Set the current environment. Set
        // a variable in the front controller indicating the current
        // environment -- commonly one of development, staging,
        // testing, production, but wholly dependent on your
        // organization's and/or site's needs.
        $frontController->setParam('env', APPLICATION_PATH);

        // BASE URL - make magic controller resolution work when you
        // are running an app below the root url (/testing/ for
        // example)
        if($GLOBALS['conf']->webroot) {
            $frontController->setBaseUrl($GLOBALS['conf']->webroot);
        }
        
        //disable Zend exception handling so that we may handle them ourselves
        $frontController->throwExceptions(true);
    }
        
    public static function setupView()
    {
        global $view;
        // LAYOUT SETUP - Setup the layout component The Zend_Layout
        // component implements a composite (or two-step-view) pattern
        // With this call we are telling the component where to find
        // the layouts scripts.
        Zend_Layout::startMvc(APPLICATION_PATH . '/v/layouts');

        // VIEW SETUP - Initialize properties of the view object The
        // Zend_View component is used for rendering views. Here, we
        // grab a "global" view instance from the layout object, and
        // specify the doctype we wish to use. In this case, XHTML1
        // Strict.
        $view = Zend_Layout::getMvcInstance()->getView();
        $view->setScriptPath(APPLICATION_PATH . '/v');
        $view->doctype('HTML4_STRICT');
    }
    
    public static function setupExceptionHandling()
    {
        //set_exception_handler(array("Framework_Controller_Action", "exceptionHandler"));
    }
    
    public static function setErrorReporting()
    {
        //ini_set('display_errors',1);
        //error_reporting(E_ALL);
        //error_reporting(E_STRICT);
        //error_reporting(E_ALL | E_STRICT);
    }

}
