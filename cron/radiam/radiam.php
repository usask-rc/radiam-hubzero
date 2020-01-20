<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

use Components\Radiam\Helpers\QueueHelper;

require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'QueueHelper.php';

/**
 * Cron plugin for radiam
 */
class plgCronRadiam extends \Hubzero\Plugin\Plugin
{
	/**
	 * Return a list of events
	 *
	 * @return  array
	 */
	public function onCronEvents()
	{
		$this->loadLanguage();

		$obj = new stdClass();
		$obj->plugin = 'radiam';

		$obj->events = array(
			array(
				'name'   => 'postApi',
				'label'  => Lang::txt('PLG_CRON_RADIAM_POST_API'),
				'params' => ''
			)
		);

		return $obj;
	}

    /**
	 * Post metadata to radiam API
	 *
	 * @param   object   $job  \Components\Cron\Models\Job
	 * @return  boolean
	 */
    public function postApi(\Components\Cron\Models\Job $job)
    {   
		$queueHelper = new QueueHelper();
        $queueHelper->processQueue();
        return true;
    }
}
