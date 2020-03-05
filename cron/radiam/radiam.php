<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

use Components\Radiam\Helpers\RadiamAgent;
use Components\Radiam\Helpers\Helper;
use Components\Radiam\Helpers\ErrorCode;
use Components\Radiam\Models\RadConfig;
use Components\Radiam\Models\RadProject;

require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'RadiamAgent.php';
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'Helper.php';
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'ErrorCode.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radconfig.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radproject.php';


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
		$obj->plugin = $this->_name;

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
	 * Post metadata to radiam API for projects whose owners are the users that have access token generated 
	 *
	 * @param   object   $job  \Components\Cron\Models\Job
	 * @return  boolean
	 */
    public function postApi(\Components\Cron\Models\Job $job)
    {   
		$logger = Helper::setLogger();
		list($config, $loadConfigStatus) = $this->_loadConfig($logger);
		if ($loadConfigStatus) {
			foreach($config['projects'] as $project_key) {
				try {
					$logger->info("Start crawling for Project {$project_key}.");
					$agent = new RadiamAgent($config, $project_key, $logger);
					list($status, $respText) = $agent->fullRun();
					
					// If the first full crawl is not executed
					if ($status == null and $respText == null) {
						$agent->processQueue();
					}
					// If the full crawling is executed for project, then don't process
					// events in the radiam queue for this project
					else {
						$agent->clearQueue();
					}
				} catch (Exception $e) {
					$this->updateRadiamQueue();
					$logger->error($e);
				} finally {					  
					$logger->info("Finish crawling for Project {$project_key}.");
				}
			}
			// Update the last crawl run time
			$db = App::get('db');
			$sql = "UPDATE `#__radiam_radconfigs` SET `last_run`=now()";
			$db->setQuery($sql);
			$db->execute();
			return true;
		}
		else {
			return false;
		}
	}
	

	/**
	 * Load radiam agent configuration from database
	 *
	 * @param object $logger
	 * @return array $config, $status
	 */
	private function _loadConfig($logger)
	{
		// Radiam Config     
		$config = array();
		
		$radconfigs = RadConfig::all();

		foreach ($radconfigs as $c) {
			$config[$c->configname] = $c->configvalue;
		}

		if (!array_key_exists('radiam_host_url', $config)) {
			// $logger->error(Lang::txt('PLG_CRON_RADIAM_ERROR_HOST_URL'));
			$logger->error("Radiam host url is not set.");
			return array($config, false);
		}

		if (!array_key_exists('agent_id', $config)) {
			$db = App::get('db');
			$config['agent_id'] = $this->generateUuid();
            $currentUserId = User::get('id');
			$sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`, `created`, `created_by`) 
					VALUES ('agent_id', '{$config['agent_id']}', now(), $currentUserId);";
			$db->setQuery($sql);
			$db->query();
		}

		if (!array_key_exists('location_name', $config)) {
			$db = App::get('db');
			$config['location_name'] = gethostname();
            $currentUserId = User::get('id');
			$sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`, `created`, `created_by`) 
					VALUES ('location_name', '{$config['location_name']}', now(), $currentUserId);";
			$db->setQuery($sql);
			$db->query();
		}

		$projects = RadProject::all();
		$config['projects'] = array();

		require_once Component::path('com_projects') . DS . 'tables' . DS . 'project.php';
		require_once Component::path('com_projects') . DS . 'helpers' . DS . 'html.php';
		$database = App::get('db');
		$obj = new \Components\Projects\Tables\Project($database);
		foreach ($projects as $project)
		{
			$project_info = array(
				'radiam_project_uuid' => $project->radiam_project_uuid, 
			);
			$project_alias = $obj->getAlias($project->project_id);
			$path = \Components\Projects\Helpers\Html::getProjectRepoPath(strtolower($project_alias), 'files');
			$project_info['rootdir'] = $path;
			$config[$project->project_id] = $project_info;
			array_push($config['projects'], $project->project_id);
		}

		return array($config, true);
	}

	/**
	 * Generate an uuid
	 *
	 * @return string
	 */
	private function generateUuid()
	{
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	/**
     * Update the last_modified field for all records in radqueue table
     *
     * @return void
     */
    private function updateRadiamQueue()
    {
		$db = App::get('db');
		$sql = "UPDATE `#__radiam_radqueue`
                SET `last_modified` = now()";
        $db->setQuery($sql);
        $db->query();
    }
}
