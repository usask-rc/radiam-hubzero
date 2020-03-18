<?php  
  
use Hubzero\Content\Migration\Base;  
  
// No direct access  
defined('_HZEXEC_') or die();  
  
/**
 * Migration script for installing default content for the Radiam module
 **/
class Migration20200318000000ModRadiam extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{	
		if ($this->db->tableExists('#__modules'))
		{
			$query = "INSERT INTO `#__modules` (`title`, `position`, `published`, `module`, `access`, `showtitle`, `client_id`)
					VALUES ('Radiam','memberDashboard', 0, 'mod_radiam', 1, 1, 0);";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
		if ($this->db->tableExists('#__modules'))
		{
			$query = "DELETE FROM `#__modules` WHERE `module`='mod_radiam'";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}
}
