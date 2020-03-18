<?php
namespace Modules\Radiam;

use Hubzero\Module\Module;
use Hubzero\Config\Registry;
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
		$this->_loadConfig();
	
		$logger = RadiamHelper::setLogger();
		$userId = User::get('id');
		$token = Radtoken::one($userId);
		if ($token === false) {
			return null;
		}
		else if ($token->expired($this)) {
			$token->refresh($this);
		}
		$tokens_array = array (
			"access"  => $token->get('access_token'),
			"refresh" => $token->get('refresh_token')
		);
		$radiam_host_url = $this->config->get('radiamurl');
		$radiamAPI = new RadiamAPI($radiam_host_url, $tokens_array, $logger, $userId);
		$projects = $radiamAPI->getProjects()->results;
		return $projects;
	}

	/**
	 * Load Radiam Component configuration
	 *
	 * @return void
	 */
	protected function _loadConfig()
    {	
		$this->config = new Registry(array());
		foreach(RadConfig::whereEquals('configname', 'radiam_host_url') as $r) {
			$this->config->set('radiamurl', $r->configvalue);
			break;
		}	
    }	
}