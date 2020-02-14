<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

use Components\Radiam\Helpers\QueueHelper;
use Components\Radiam\Models\Radtoken;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'QueueHelper.php';

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
				$queueHelper = new QueueHelper($config, $project_key, $logger);
				$queueHelper->processQueue();
			}
			return true;
		}
		else {
			return false;
		}
	}
	
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
			$sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`, `created`) 
					VALUES ('agent_id', '{$config['agent_id']}', now());";
			$db->setQuery($sql);
			$db->query();
		}

		$sql = "SELECT `project_id`, `radiam_project_uuid`, `radiam_user_uuid` FROM `#__radiam_radprojects`";
		$db->setQuery($sql);
		$projects = $db->loadObjectList();
		$config['projects'] = array();
		foreach ($projects as $project)
		{
			$project_info = array(
				'radiam_project_uuid' => $project->radiam_project_uuid, 
				'radiam_user_uuid' => $project->radiam_user_uuid
			);
			$config[$project->project_id] = $project_info;
			array_push($config['projects'], $project->project_id);

			// TODO: move this to checkin function
			$config[$project->project_id]['endpoint'] = 'https://dev2.radiam.ca/api/projects/' . $config[$project->project_id]['radiam_project_uuid'] . '/';
			
			// TODO: move this 
			$config['location']['id'] = 'b3271c0c-7794-43bb-980b-870887bd2414';
		}
		return array($config, true);
	}

	protected function _setLogger()
    {
        $logger = new Logger(Config::get('application_env'));
        $streamHandler = new StreamHandler(Config::get('log_path', PATH_APP . DS . 'logs') . '/radiam.log', Logger::DEBUG);

        $logFormatter = "%datetime% [%level_name%] %message%\n";
        $formatter = new LineFormatter($logFormatter);
        $streamHandler->setFormatter($formatter);
        $logger->pushHandler($streamHandler);

        return $logger;
	}
	protected function generateUuid()
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
}
