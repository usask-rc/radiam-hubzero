<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

/**
 * projects radiam plugin
 */
class plgProjectsRadiam extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 */
	protected $_autoloadLanguage = false;

	/**
	 * Event call for a specific project
	 *
	 * @param   object  $model   Project model
	 * @param   string  $action  Plugin task
	 * @param   string  $areas   Plugins to return data
	 * @return  void
	 */
	public function onProject($model, $action = '', $areas = null)
	{
		// Trigger Radiam Component if uploading, deleteing, moving or renaming files, creating new directories
		$target_actions = array("save", "saveprov", "removeit", "moveit", "renameit", "savedir");
		if (in_array($action, $target_actions, true)) 
		{
			$db = App::get('db');

			# FOR TESTING ACTION PURPOSE
			// $sql_test = "INSERT INTO `#__radiam_radprojects` (`radiam_project_uuid`, `radiam_user_uuid`, `radiam_token`, `created`)
			// 		VALUES ('{$action}', '666', '666', now())";
			// $db->setQuery($sql_test);
			// $db->execute(); 

			# Update the last files event time
			$sql = "UPDATE `#__radiam_radconfigs` SET `last_run`=now() WHERE `configname`='radiam_host_url'";
			$db->setQuery($sql);
			$db->execute();  
			
		}		
		return;
	}
}
