<?php

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH',
        realpath(dirname(__FILE__) . '/../'));
              
// Determine potential env:
$env = getenv('APPLICATION_ENVIRONMENT') ? getenv('APPLICATION_ENVIRONMENT') : 'dev';
if ($env == 'qa' && file_exists(APPLICATION_PATH . '/ENV_DEMO')) {
    $env = 'demo';
}

// Define application environment
defined('APPLICATION_ENVIRONMENT')
    || define('APPLICATION_ENVIRONMENT', $env);

// clean up after ourselves
unset($env);

// Set include path to include all necessary libs, ect
set_include_path(
    implode(PATH_SEPARATOR, array(
        APPLICATION_PATH . '/m',
        APPLICATION_PATH . '/lib',
        get_include_path()
    ))
);

/** Zend_Autoloader */
require_once "Zend/Loader/Autoloader.php";
global $autoloader;
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setFallbackAutoloader(true);
try{
// Create application, bootstrap, and run
require_once "Zend/Application.php";
$application = new Zend_Application( APPLICATION_ENVIRONMENT, APPLICATION_PATH.'/conf/application.ini');
$application->bootstrap()
            ->run();
            }catch (Exception $e) {
                print $e->getMessage();
                exit();
            }