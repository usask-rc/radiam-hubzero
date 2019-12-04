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
		$items = $this->getItems($this->params->get('usercount', 5));

		require $this->getLayoutPath();
	}

	/**
	 * Retrieves items from the database
	 *
	 * @param   integer  $userCount  The number of items to return
	 * @return  array
	 */
	public function getItems($userCount)
	{
		// Get a reference to the database
		$db = App::get('db');

		// Get a list of $userCount randomly ordered users 
		$query = 'SELECT a.name FROM `#__users` AS a ORDER BY rand() LIMIT ' . intval($userCount)  . '';

		$db->setQuery($query);
		$items = $db->loadObjectList();
		$items = ($items) ? $items : array();

		return $items;
	}
}