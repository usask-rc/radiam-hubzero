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
class RadiamHelper
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
        if (!App::has('log')) {
            $logger = new Logger(Config::get('application_env'));
            $streamHandler = new StreamHandler(Config::get('log_path', PATH_APP . DS . 'logs') . '/radiam.log', Logger::INFO);

            $logFormatter = "%datetime% [%level_name%] %message%\n";
            $formatter = new LineFormatter($logFormatter);
            $streamHandler->setFormatter($formatter);
            $logger->pushHandler($streamHandler);

            return $logger;
		}
        else {
            // This method is only called once per request
            App::get('log')->register('radiam', array(
                'file'       => 'radiam.log',
                'level'      => 'info',
                'format'     => "%datetime% [%level_name%] %message%\n"
            ));
            return App::get('log')->logger('radiam');
        }
    }
    
    /**
	 * Generate an uuid
	 *
	 * @return string
	 */
	public static function generateUuid()
	{
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
} 