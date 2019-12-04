<?php
namespace Modules\Radiam;

require_once __DIR__ . '/helper.php';

with(new Helper($params, $module))->display();
