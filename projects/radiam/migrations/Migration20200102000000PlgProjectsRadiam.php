<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

use Hubzero\Content\Migration\Base;

// No direct access
defined('_HZEXEC_') or die();

/**
 * Migration script for adding entry for projects radiam plugin
 **/
class Migration20200102000000PlgProjectsRadiam extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
		$this->addPluginEntry('projects', 'radiam');

		# The order of executing the radiam plugin has to be smaller than that of the files plugin 
		# when the onProject event is triggered. If not, some actions such as removeit and renameit
		# cannot be received. Therefore, the ordering is set to zero. 
		$query = "UPDATE `#__extensions` SET `ordering`='0' WHERE `folder`='projects' AND `element`='radiam'";
		$this->db->setQuery($query);
		$this->db->query();
	}

	/**
	 * Down
	 **/
	public function down()
	{
		$this->deletePluginEntry('projects', 'radiam');
	}
}
