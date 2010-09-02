<?php


// www/index.php
//
// Step 1: APPLICATION_PATH is a constant pointing to our
// application/subdirectory. We use this to add our "library" directory
// to the include_path, so that PHP can find our Zend Framework classes.


define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../'));

set_include_path(
    
    // include models path
    APPLICATION_PATH . '/m' . PATH_SEPARATOR .

    // include library path
    APPLICATION_PATH . '/lib' . PATH_SEPARATOR .

    get_include_path()
    );

// autoloader
require_once "Zend/Loader/Autoloader.php";
global $autoloader;
$autoloader = Zend_Loader_Autoloader::getInstance();

// bootstrap
try {
  require '../conf/ApplicationBootstrap.php';
  ApplicationBootstrap::strap();
} catch (Exception $exception) {
    echo '<html><body><center>'
      . 'An exception occured while bootstrapping the application.';
    if (defined('APPLICATION_ENVIRONMENT')
        && APPLICATION_ENVIRONMENT != 'production'
	) {
      echo '<br /><br />' . $exception->getMessage() . '<br />'
           . '<div align="left">Stack Trace:' 
	. '<pre>' . $exception->getTraceAsString() . '</pre></div>';
    }
    echo '</center></body></html>';
    error_log($exception);
    exit(1);
}



$viewRenderer =
    Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
$viewRenderer->setViewSuffix('tpl');


//Step 4: DISPATCH:  Dispatch the request using the front controller.
// The front controller is a singleton, and should be setup by now. We 
// will grab an instance and call dispatch() on it, which dispatches the
// current request.
$frontController = Zend_Controller_Front::getInstance();


// access plugin
//require_once "Framework/Auth/AccessPlugin.php"; 
//$frontController->registerPlugin(new Framework_Auth_AccessPlugin(), 1);


// impersonation
//global $conf;
//$router = $frontController->getRouter();
//$router->addConfig($conf, 'routes');


// actual dispatch
$frontController->dispatch();
