<?php
namespace Modules\Radiam;

use Hubzero\Module\Module;
use App;

class Helper extends Module
{
	/**
	 * Display module contents
	 *
	 * @return  void
	 */
	public function display()
	{
		$limit = intval($this->params->get('projectcount', 'mod_radiam'));
		if ($limit == 0) { $limit = 5; }
		$projects = $this->getRadProjects($limit);

		require $this->getLayoutPath();
	}

	/**
	 * Retrieves projects from the database
	 *
	 * @param   integer  $count  The number of projects to return
	 * @return  array
	 */
	public function getRadProjects($count)
	{
		// Get a reference to the database
		$db = App::get('db');

		// Get a list of Projects
		$query = 'SELECT * FROM `#__radiam_radprojects` LIMIT ' . intval($count) . '';

		$db->setQuery($query);
		$projects = $db->loadObjectList();
		$projects = ($projects) ? $projects : array();

		return $projects;
	}
}