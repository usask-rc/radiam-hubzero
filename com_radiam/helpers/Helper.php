<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Radiam\Helpers;

// No direct access
defined('_HZEXEC_') or die('Restricted access');

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
} 