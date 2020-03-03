<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Radiam\Helpers;

// No direct access
defined('_HZEXEC_') or die('Restricted access');

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * General Helper functions
 *
 */
class Helper
{   
    /**
	 * Return a combined url (endpoint)
	 *
	 * @return  string
	 */
    public static function buildUrl($baseUrl, $apiPath)
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($apiPath, '/');
    }

    /**
	 * Set up the logger
	 *
	 * @return object $logger The logger
	 */
	public static function setLogger()
    {   
        $logger = new Logger(Config::get('application_env'));
        $streamHandler = new StreamHandler(Config::get('log_path', PATH_APP . DS . 'logs') . '/radiam.log', Logger::DEBUG);

        $logFormatter = "%datetime% [%level_name%] %message%\n";
        $formatter = new LineFormatter($logFormatter);
        $streamHandler->setFormatter($formatter);
        $logger->pushHandler($streamHandler);

        return $logger;
	}
} 