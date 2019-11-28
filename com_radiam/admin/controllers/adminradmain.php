<?php
namespace Components\Radiam\Admin\Controllers;

use Hubzero\Component\AdminController;
use Components\Radiam\Models\RadConfig;
use Components\Radiam\Models\RadProject;
use Request;
use Notify;
use Route;
use Lang;
use App;

/**
 * Radiam controller for admin main page. It only has a display task,
 * editing is handled by the other controllers.
 * 
 */
class Adminradmain extends AdminController
{
	/**
	 * Execute a task
	 *
	 * @return  void
	 */
	public function execute()
	{
		// Here we're equating the task 'add' to 'edit'. When examing
		// this controller, you should not find any method called 'addTask'.
		// Instead, we're telling the controller to execute the 'edit' task
		// whenever a task of 'add' is called.
		$this->registerTask('add', 'edit');
		$this->registerTask('apply', 'save');
		$this->registerTask('publish', 'state');
		$this->registerTask('unpublish', 'state');

		// Call the parent execute() method. Important! Otherwise, the
		// controller will never actually execute anything.
		parent::execute();
	}

	/**
	 * Display a list of entries
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// Get the incoming filters to apply to the entries list
		//
		$filters = array(
			'search' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.search',
				'search',
				''
			)),
			'state' => urldecode(Request::getState(
				$this->_option . '.' . $this->_controller . '.state',
				'state',
				-1
			)),
			// Get sorting variables
			'sort' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sort',
				'filter_order',
				'configname'
			),
			'sort_Dir' => Request::getState(
				$this->_option . '.' . $this->_controller . '.sortdir',
				'filter_order_Dir',
				'ASC'
			)
		);

		// The model to use for paging and sorting is projects
		$record = RadProject::all();
		if ($filters['state'] >= 0)
		{
			$record->whereEquals('state', $filters['state']);
		}
		if ($search = $filters['search'])
		{
			$record->whereLike('configname', $search);
		}
		$rows = $record
			->ordered('filter_order', 'filter_order_Dir')
			->paginated();

		// Grab the Radiam global configs, but no paging or sorting
		$configs = RadConfig::all();

		// Output the view
		$this->view
			->set('filters', $filters)
			->set('rows', $rows)
			->set('configs', $configs)
			->display();
	}

}
