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
 * Raidam process radqueue table helper functions
 *
 */
class Helper
{   
    public static function buildUrl($baseUrl, $apiPath)
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($apiPath, '/');
    }
} 