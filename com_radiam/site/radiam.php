<?php
// Declare the namespace.
namespace Components\Radiam\Site;

// Include our model. Models are almost always singular in name.
//
include_once dirname(__DIR__) . '/models/radconfig.php';

// We'll load in our controller. This example component only has
// one controller, so we can declare it specifically.
//
include_once __DIR__ . '/controllers/radiamsitemain.php';

// Build the class name
//
// Class names are namespaced and follow the directory structure:
//
// Components\{Component name}\{Client name}\{Directory name}\{File name}
//
// So, for a controller with the name of "radconfig" in this component:
//
// /com_radiam
//    /site
//        /controllers
//            /radconfig.php
//
// ... we get the final class name of "Components\Radiam\Site\Controllers\RadConfig".
//

$controllerName = __NAMESPACE__ . '\\Controllers\\Radiamsitemain';

// Instantiate the controller
$component = new $controllerName();

// This detects the incoming task and executes it if it can. If no task 
// is set, it will execute a default task of "display" which maps to a 
// method of "displayTask" in the controller.
$component->execute();
