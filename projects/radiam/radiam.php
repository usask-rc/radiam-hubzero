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
		$db = App::get('db');
		
		// Query the ordering of radiam plugin and files plugin
		$query_ordering_files = "SELECT `ordering` FROM `#__extensions` WHERE `folder`='projects' AND `element`='files'";
		$db->setQuery($query_ordering_files);
		$ordering_files = $db->loadObject()->ordering;

		$query_ordering_radiam = "SELECT `ordering` FROM `#__extensions` WHERE `folder`='projects' AND `element`='radiam'";
		$db->setQuery($query_ordering_radiam);
		$ordering_radiam = $db->loadObject()->ordering;

		// The order of executing the radiam plugin has to be smaller than that of the files plugin 
		// when the onProject event is triggered. If not, some actions such as removeit, moveit and renameit
		// cannot be received. Instead, actions delete, move and rename will be used to trigger 
		// radiam component. The difference between action delete and removeit is that the delete action is
		// triggered when users open the delete ajax box, while the removeit action is triggered when users 
		// click on the Delete button. 
		if ($ordering_files > $ordering_radiam)
		{
			$target_actions = array("save", "saveprov", "removeit", "moveit", "renameit", "savedir");
		}
		else 
		{
			$target_actions = array("save", "saveprov", "delete", "move", "rename", "savedir");
		}

		// Trigger Radiam Component if uploading, deleteing, moving or renaming files, creating new directories
		if (in_array($action, $target_actions, true)) 
		{
			// FOR TESTING ACTION PURPOSE
			// $sql_test = "INSERT INTO `#__radiam_radprojects` (`radiam_project_uuid`, `radiam_user_uuid`, `radiam_token`, `created`)
			// 			 VALUES ('{$action}', '{$ordering_files}', '{$ordering_radiam}', now())";
			// $db->setQuery($sql_test);
			// $db->execute(); 

			// Update the last files event time
			$sql = "UPDATE `#__radiam_radconfigs` SET `last_run`=now() WHERE `configname`='radiam_host_url'";
			$db->setQuery($sql);
			$db->execute();  
		}		
		return;
	}
}
