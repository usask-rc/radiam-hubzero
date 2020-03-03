<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

use Hubzero\Content\Migration\Base;

// No direct access
defined('_HZEXEC_') or die();

/**
 * Migration script for installing default radiam cron job
 **/
class Migration20200302000000PlgCronRadiam extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
		if ($this->db->tableExists('#__cron_jobs'))
		{
			$query = "SELECT `id` FROM `#__cron_jobs` WHERE `plugin`='radiam' AND `event`='postApi';";
			$this->db->setQuery($query);
			$id = $this->db->loadResult();

			if (!$id)
			{
				$query = "INSERT INTO `#__cron_jobs` (`title`, `state`, `plugin`, `event`, `last_run`, `next_run`, `recurrence`, `created`, `created_by`, `modified`, `modified_by`, `active`, `ordering`, `params`) 
						  VALUES ('Post data to Radiam API', 0, 'radiam', 'postApi', NULL, NULL, '*/15 * * * *', now(), 0, now(), 0, 0, 0, '');";

				$this->db->setQuery($query);
				$this->db->query();
			}
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
		if ($this->db->tableExists('#__cron_jobs'))
		{
			$query = "DELETE FROM `#__cron_jobs` WHERE `plugin`='radiam' AND `event`='postApi';";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}
}
