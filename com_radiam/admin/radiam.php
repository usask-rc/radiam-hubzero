<?php
namespace Components\Radiam\Admin;

// This is a permissions check to make sure the current logged-in
// user has permission to even access this component. Components
// can be blocked from users in a specific access group. This is 
// particularly important for components that can have potentially
// dramatic effects on users and the site (such as the members 
// component or plugin manager).
if (!\User::authorise('core.manage', 'com_radiam'))
{
	return \App::abort(404, \Lang::txt('JERROR_ALERTNOAUTHOR'));
}

// NOTE: We're using the __DIR__ constant. This is a constant
// automatically defined in PHP 5.3+. Its value is the absolute
// path up to the directory that this file is in.
require_once dirname(__DIR__) . '/models/radconfig.php';

// Get the permissions helper.
//
// This is a class that tries into the permissions ACL and 
// we'll use it to determine if the current logged-in user has 
// permission to perform certain actions such as edit, delete, etc.
require_once dirname(__DIR__) . '/helpers/permissions.php';

// We'll load in our controller. This exampel component only has
// one controller, so we can declare it specifically. Frequently
// components will have mroe than one controller as a way to help
// group and organize related code.
//
// Controllers are generally plural in name.
require_once __DIR__ . '/controllers/radconfig.php';

// Build the class name
//
// Class names are namespaced and follow the directory structure:
//
// Components\{Component name}\{Client name}\{Directory name}\{File name}
//
// So, for a controller with the name of "show" in this component:
//
// /com_radiam
//    /site
//        /controllers
//            /radconfig.php
//
// ... we get the final class name of "Components\Radiam\Site\Controllers\RadConfig".
//
// Typically, directories are plural (controllers, models, tables, helpers).
$controllerName = __NAMESPACE__ . '\\Controllers\\AdminRadConfig';

// Instantiate controller
$controller = new $controllerName();

// This detects the incoming task and executes it if it can. If no task 
// is set, it will execute a default task of "display" which maps to a 
// method of "displayTask" in the controller.
$controller->execute();
