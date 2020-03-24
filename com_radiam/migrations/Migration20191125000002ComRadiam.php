<?php

use Hubzero\Content\Migration\Base;

// No direct access
defined('_HZEXEC_') or die();

/**
 * Migration script for installing default content for the Radiam component
 **/
class Migration20191125000002ComRadiam extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{	
		$currentUserId = User::get('id');
		if ($this->db->tableExists('#__radiam_radconfigs'))
		{
			$query = "INSERT INTO `#__radiam_radconfigs` (`id`, `configname`, `configvalue`, `created`, `created_by`, `state`)
					VALUES (1,'radiam_host_url', 'https://radiam.somewhere.edu/', now(), '{$currentUserId}', 1);";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
		if ($this->db->tableExists('#__radiam_radconfigs'))
		{
			$query = "DELETE FROM `#__radiam_radconfigs`";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}
}
