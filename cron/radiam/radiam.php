<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

use Components\Radiam\Helpers\RadiamAgent;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'RadiamAgent.php';

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
		$logger = $this->_setLogger();
		list($config, $loadConfigStatus) = $this->_loadConfig($logger);
		if ($loadConfigStatus) {
			foreach($config['projects'] as $project_key) {
				try {
					$logger->info("Start crawling for Project {$project_key}.");
					$agent = new RadiamAgent($config, $project_key, $logger);
					$agent->fullRun();
					$agent->processQueue();
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
		
		$db = App::get('db');
		$sql = "SELECT `configname`, `configvalue` FROM `#__radiam_radconfigs`";
		$db->setQuery($sql);
		$configsDb = $db->loadObjectList();

		foreach ($configsDb as $c) {
			$config[$c->configname] = $c->configvalue;
		}

		if (!array_key_exists('radiam_host_url', $config)) {
			$logger->error("Radiam host url is not set.");
			return array($config, false);
		}

		if (!array_key_exists('agent_id', $config)) {
			$config['agent_id'] = $this->generateUuid();
			$sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`) 
					VALUES ('agent_id', '{$config['agent_id']}');";
			$db->setQuery($sql);
			$db->query();
		}

		if (!array_key_exists('location_name', $config)) {
			$config['location_name'] = gethostname();
			$sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`) 
					VALUES ('location_name', '{$config['location_name']}');";
			$db->setQuery($sql);
			$db->query();
		}

		$sql = "SELECT `project_id`, `radiam_project_uuid`, `radiam_user_uuid` FROM `#__radiam_radprojects`";
		$db->setQuery($sql);
		$projects = $db->loadObjectList();
		$config['projects'] = array();

		require_once Component::path('com_projects') . DS . 'tables' . DS . 'project.php';
		require_once Component::path('com_projects') . DS . 'helpers' . DS . 'html.php';
		$database = App::get('db');
		$obj = new \Components\Projects\Tables\Project($database);
		foreach ($projects as $project)
		{
			$project_info = array(
				'radiam_project_uuid' => $project->radiam_project_uuid, 
				'radiam_user_uuid' => $project->radiam_user_uuid
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
	 * Set up the logger
	 *
	 * @return object $logger The logger
	 */
	private function _setLogger()
    {
        $logger = new Logger(Config::get('application_env'));
        $streamHandler = new StreamHandler(Config::get('log_path', PATH_APP . DS . 'logs') . '/radiam.log', Logger::DEBUG);

        $logFormatter = "%datetime% [%level_name%] %message%\n";
        $formatter = new LineFormatter($logFormatter);
        $streamHandler->setFormatter($formatter);
        $logger->pushHandler($streamHandler);

        return $logger;
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
