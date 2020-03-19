<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

use Components\Radiam\Helpers\RadiamAgent;
use Components\Radiam\Helpers\RadiamHelper;
use Components\Radiam\Helpers\ErrorCode;
use Components\Radiam\Models\RadConfig;
use Components\Radiam\Models\RadProject;

require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'RadiamAgent.php';
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'RadiamHelper.php';
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'ErrorCode.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radconfig.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radproject.php';


/**
 * Cron plugin for radiam
 */
class plgCronRadiam extends \Hubzero\Plugin\Plugin
{	
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 */
	protected $_autoloadLanguage = true;


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
		$logger = RadiamHelper::setLogger();
		$logger->info("Running the Radiam Cron Job postApi...");
		try {
			$config = $this->_loadConfig($logger);

			// Crawl all the projects that have been associated with radiam projects
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
					$this->updateRadiamQueue($project_key);
					$logger->error($e->getMessage());
				} finally {					  
					$logger->info("Finish crawling for Project {$project_key}.");
				}
			}
			$this->updateRadiamLastrun();
			return true;
		} catch (Exception $e) {
			$logger->error($e->getMessage());
			// TODO: decide whether throw the exception or notify it as an message
			// \Notify::error($e->getMessage());
			throw $e;
			// TODO: decide whether return false. If return false, the cron job will be active. No way to run it until deactivate it.
			return false;
		} finally {
			$logger->info("Finish running the Radiam Cron Job postApi.");
		}
	}
	

	/**
	 * Load radiam agent configuration from database
	 *
	 * @param object $logger
	 * @return array $config, $status
	 * @throws Exception if radiam_host_url is not configured
	 */
	private function _loadConfig($logger)
	{	
		$logger->info("Loading Radiam Configurations...");
		
		// Radiam Config     
		$config = array();
		
		$radconfigs = RadConfig::all();

		foreach ($radconfigs as $c) {
			$config[$c->configname] = $c->configvalue;
		}

		if (!array_key_exists('radiam_host_url', $config)) {
			throw new Exception(Lang::txt('PLG_CRON_RADIAM_ERROR_HOST_URL'), ErrorCode::NOT_FOUND_ERROR);
		}

		if (!array_key_exists('agent_id', $config)) {
			$db = App::get('db');
			$config['agent_id'] = Helper::generateUuid();
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
				'radiam_project_name' => $project->radiam_project_name,
			);
			$project_alias = $obj->getAlias($project->project_id);
			$path = \Components\Projects\Helpers\Html::getProjectRepoPath(strtolower($project_alias), 'files');
			$project_info['rootdir'] = $path;
			$config[$project->project_id] = $project_info;
			array_push($config['projects'], $project->project_id);
		}
		return $config;
	}

	/**
     * Update the last_modified field for all records of the given project in radqueue table
     *
	 * @param int $project_key
     * @return void
     */
    private function updateRadiamQueue($project_key)
    {
		$db = App::get('db');
		$sql = "UPDATE `#__radiam_radqueue`
				SET `last_modified` = now()
				WHERE `project_id` = '{$project_key}'";
        $db->setQuery($sql);
        $db->query();
	}
	
	/**
     * Update the last_run time of the radiam agent
     *
     * @return void
     */
	private function updateRadiamLastrun()
	{
		$db = App::get('db');
		$sql = "UPDATE `#__radiam_radconfigs` SET `last_run`=now()";
		$db->setQuery($sql);
		$db->execute();
	}
}
