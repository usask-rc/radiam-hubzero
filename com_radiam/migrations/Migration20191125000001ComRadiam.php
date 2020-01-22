<?php

use Hubzero\Content\Migration\Base;

// No direct access
defined('_HZEXEC_') or die();

/**
 * Migration script for installing tables for Radiam component
 **/
class Migration20191125000001ComRadiam extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
		$this->addComponentEntry('radiam');

		if (!$this->db->tableExists('#__radiam_radconfigs'))
		{
			$query = "CREATE TABLE IF NOT EXISTS `#__radiam_radconfigs` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `configname` varchar(255) NOT NULL DEFAULT '',
			  `configvalue` varchar(255) NOT NULL DEFAULT '',
			  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			  `created_by` int(11) unsigned NOT NULL DEFAULT '0',
			  `state` tinyint(2) NOT NULL DEFAULT '1',
			  `last_run` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			  PRIMARY KEY (`id`),
			  KEY `idx_state` (`state`),
			  KEY `idx_created_by` (`created_by`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

			$this->db->setQuery($query);
			$this->db->query();
		}

		if (!$this->db->tableExists('#__radiam_radprojects'))
		{
			$query = "CREATE TABLE IF NOT EXISTS `#__radiam_radprojects` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `project_id` int(11) unsigned NOT NULL DEFAULT '0',
			  `radiam_project_uuid` varchar(80) NOT NULL DEFAULT '',
			  `radiam_user_uuid` varchar(80) NOT NULL DEFAULT '',
			  `radiam_token` TEXT NOT NULL DEFAULT '',
			  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			  `created_by` int(11) unsigned NOT NULL DEFAULT '0',
			  `state` tinyint(2) NOT NULL DEFAULT '1',
			  PRIMARY KEY (`id`),
			  KEY `idx_state` (`state`),
			  KEY `idx_project` (`project_id`),
			  KEY `idx_created_by` (`created_by`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

			$this->db->setQuery($query);
			$this->db->query();
		}

		if (!$this->db->tableExists('#__radiam_radtokens'))
		{
			$query = "CREATE TABLE IF NOT EXISTS `#__radiam_radtokens` (
			  `user_id` int(11) unsigned NOT NULL,
			  `access_token` text DEFAULT '',
			  `refresh_token` text DEFAULT '',
			  `valid_until` datetime DEFAULT '0000-00-00 00:00:00',
			  PRIMARY KEY (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

			$this->db->setQuery($query);
			$this->db->query();
		}

		if (!$this->db->tableExists('#__radiam_radqueue'))
		{
			$query = "CREATE TABLE IF NOT EXISTS `#__radiam_radqueue` (
			`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`project_id` int(11) unsigned NOT NULL DEFAULT '0',
			`path` varchar(255) NOT NULL DEFAULT '',
			`action` VARCHAR(80) DEFAULT NULL,		
			`created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`last_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (`id`),
			KEY `idx_project` (`project_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

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
			$query = "DROP TABLE #__radiam_radconfigs";
			$this->db->setQuery($query);
			$this->db->query();
		}

		if ($this->db->tableExists('#__radiam_radprojects'))
		{
			$query = "DROP TABLE #__radiam_radprojects";
			$this->db->setQuery($query);
			$this->db->query();
		}

		if ($this->db->tableExists('#__radiam_radtokens'))
		{
			$query = "DROP TABLE #__radiam_radtokens";
			$this->db->setQuery($query);
			$this->db->query();
		}

		if ($this->db->tableExists('#__radiam_radqueue'))
		{
			$query = "DROP TABLE #__radiam_radqueue";
			$this->db->setQuery($query);
			$this->db->query();
		}

		$this->deleteComponentEntry('radiam');
	}
}
