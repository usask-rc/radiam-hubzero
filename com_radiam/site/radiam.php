<?php
namespace Components\Radiam\Site;

require_once dirname(__DIR__) . DS . 'models' . DS . 'file.php';
require_once dirname(__DIR__) . DS . 'models' . DS . 'files.php';
require_once dirname(__DIR__) . DS . 'models' . DS . 'project.php';
require_once dirname(__DIR__) . DS . 'models' . DS . 'projects.php';
require_once dirname(__DIR__) . DS . 'models' . DS . 'radtoken.php';
require_once dirname(__DIR__) . DS . 'models' . DS . 'radconfig.php';
require_once dirname(__DIR__) . DS . 'models' . DS . 'radproject.php';

$controllerName = \Request::getCmd('controller', \Request::getCmd('view', 'radiam'));
if (!file_exists(__DIR__ . DS . 'controllers' . DS . $controllerName . '.php'))
{
    $controllerName = 'radiam';
}
require_once __DIR__ . DS . 'controllers' . DS . $controllerName . '.php';
$controllerName = __NAMESPACE__ . '\\Controllers\\' . ucfirst(strtolower($controllerName));

// Instantiate controller
$controller = new $controllerName();
$controller->execute();
