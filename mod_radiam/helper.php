<?php
namespace Modules\Radiam;

use Hubzero\Module\Module;
use App;
use Components\Radiam\Helpers\RadiamHelper;
use Components\Radiam\Helpers\RadiamAPI;
use Components\Radiam\Models\RadConfig;
use Components\Radiam\Models\Radtoken;

require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'RadiamHelper.php';
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'radiam_api.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radtoken.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radconfig.php';

class Helper extends Module
{
	/**
	 * Display module contents
	 *
	 * @return  void
	 */
	public function display()
	{	
		// Get the module parameters
		$params = $this->params;
		$this->moduleclass = $params->get('moduleclass', '');
		$limit = intval($params->get('limit', 5));
		$projects = $this->getRadProjects($limit);
		$this->projects = $projects;
		$this->limit= $limit;
		$this->total = count($projects);
		require $this->getLayoutPath();
	}

	/**
	 * Retrieves projects from the Radiam API
	 *
	 * @param   integer  $count  The number of projects to return
	 * @return  array
	 */
	public function getRadProjects($count)
	{
		// // Get a reference to the database
		// $db = App::get('db');

		// // Get a list of Projects
		// $query = 'SELECT * FROM `#__radiam_radprojects` LIMIT ' . intval($count) . '';

		// $db->setQuery($query);
		// $projects = $db->loadObjectList();
		// $projects = ($projects) ? $projects : array();
		// return $projects;

		foreach(RadConfig::whereEquals('configname', 'radiam_host_url') as $r) {
			$radiam_host_url = $r->configvalue;
			break;
		}		
		$logger = RadiamHelper::setLogger();
		$userId = User::get('id');
		try {
			$token = Radtoken::oneOrFail($userId);
		} catch (\Exception $e) {
			return null;
		}
		$tokens_array = array (
			"access"  => $token->get('access_token'),
			"refresh" => $token->get('refresh_token')
		);
		$radiamAPI = new RadiamAPI($radiam_host_url, $tokens_array, $logger, $userId);
		$projects = $radiamAPI->getProjects()->results;
		return $projects;
	}
}