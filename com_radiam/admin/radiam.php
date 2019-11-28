<?php
namespace Components\Radiam\Admin;

// This is a permissions check to make sure the current logged-in
// user has permission to even access this component.
if (!\User::authorise('core.manage', 'com_radiam'))
{
	return \App::abort(404, \Lang::txt('JERROR_ALERTNOAUTHOR'));
}

// Load the models
require_once dirname(__DIR__) . '/models/radconfig.php';
require_once dirname(__DIR__) . '/models/radproject.php';

// Get the permissions helper.
// This is a class that ties into the permissions ACL and 
// we'll use it to determine if the current logged-in user has 
// permission to perform certain actions such as edit, delete, etc.
require_once dirname(__DIR__) . '/helpers/permissions.php';

// Load in the correct admin controller.
$controllerName = \Request::getCmd('controller', 'adminradmain');
if (!file_exists(__DIR__ . DS . 'controllers' . DS . $controllerName . '.php'))
{
        $controllerName = 'adminradmain';
}
require_once __DIR__ . DS . 'controllers' . DS . $controllerName . '.php';

// Build the class name
//
// Class names are namespaced and follow the directory structure:
//
// Components\{Component name}\{Client name}\{Directory name}\{File name}
//
$controllerName = __NAMESPACE__ . '\\Controllers\\' . ucfirst($controllerName);

// Instantiate controller
$controller = new $controllerName();

// This detects the incoming task and executes it if it can. If no task 
// is set, it will execute a default task of "display" which maps to a 
// method of "displayTask" in the controller.
$controller->execute();
