<?php
// Declare the namespace.
namespace Components\Radiam\Site\Controllers;

use Hubzero\Component\SiteController;
use Components\Radiam\Models\RadConfig;
use Request;
use Notify;
use Lang;
use User;
use App;

/**
 * Radiam site controller
 * 
 */
class Radiamsitemain extends SiteController
{
	/**
	 * Determine task to perform and execute it.
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

		// Call the parent execute() method. Important! Otherwise, the
		// controller will never actually execute anything.
		parent::execute();
	}

	/**
	 * Default task. Displays a list of characters.
	 *
	 * @return  void
	 */
	public function displayTask()
	{
		// NOTE:
		// A \Hubzero\Component\View object is auto-created when calling
		// execute() on the controller. By default, the view directory is 
		// set to the controller name and layout is set to task name.
		//
		// controller=foo&task=bar   loads a view from:
		//
		//   view/
		//     foo/
		//       tmpl/
		//         bar.php
		//
		// A new layout or name can be chosen by calling setLayout('newlayout')
		// or setName('newname') respectively.

		// This is the entry point to the database and the 
		// table of radconfig we'll be retrieving data from
		//
		// Here we're selecting all radconfig records, ordered, and paginated.
		$records = RadConfig::all()
			->ordered()
			->paginated()
			->rows();

		// Output the view
		// 
		// Make sure we load the correct view. This is for cases where 
		// we may be redirected from editTask(), which can happen if the
		// user is not logged in.
		$this->view
			->set('records', $records)
			->setLayout('display')
			->display();
	}

	/**
	 * Display
	 *
	 * @return  void
	 */
	public function viewTask()
	{
		// Load a single record for display.
		// If the record doesn't exist, an error will be thrown.
		$model = RadConfig::oneOrFail(Request::getInt('id', 0));

		// Output the view
		$this->view
			->set('model', $model)
			->display();
	}

	/**
	 * Display a form for editing or creating an entry.
	 *
	 * @param   object  $model  RadConfig
	 * @return  void
	 */
	public function editTask($model=null)
	{
		// Only logged in users!
		if (User::isGuest())
		{
			App::abort(403, Lang::txt('COM_RADIAM_ERROR_UNAUTHORIZED'));
		}

		// If we're being passed an object, use it instead
		// This means we came from saveTask() and some error occurred.
		// Most likely a missing or incorrect field.
		//
		// If not object passed, then we're most likely creating a new
		// record or editing one for the first time.
		if (!($model instanceof RadConfig))
		{
			// Grab the incoming ID and load the record for editing
			$model = RadConfig::oneOrNew(Request::getInt('id', 0));
		}

		// Pass any received errors to the view
		// These will be coming from the editTask()
		foreach ($this->getErrors() as $error)
		{
			Notify::error($error);
		}

		// Output the view
		// 
		// Make sure we load the edit view.
		// This is for cases where saveTask() might encounter a data
		// validation error and fall through to editTask(). Since layout 
		// is auto-assigned the task name, the layout will be 'save' but
		// saveTask has no layout!
		$this->view
			->set('model', $model)
			->setLayout('edit')
			->display();
	}

	/**
	 * Save a character entry to the database and redirect back to
	 * the main view
	 *
	 * @return  void
	 */
	public function saveTask()
	{
		// Only logged in users!
		if (User::isGuest())
		{
			App::abort(403, Lang::txt('COM_RADIAM_ERROR_UNAUTHORIZED'));
		}

		// [SECURITY] This is a Cross-Site Request Forgery token check
		//
		// This will check if:
		//    1) a CSRF token was passed in the form and
		//    2) the token was valid and tied to the proper user
		//
		// If it fails, it will throw an exception.
		Request::checkToken();

		// Incoming data, specifically from POST
		$data = Request::getVar('entry', array(), 'post');

		// Bind the incoming data to our model
		//
		// Here, we're calling "oneOrNew" which accepts an ID.
		// If no ID is set, it will return an object with empty values
		// (a new record) otherwise it will attempt to load a record
		// with the specified ID and bind its data to the model.
		//
		// We then set (overwrite) any data on the model with the data
		// coming from the edit form.
		$model = RadConfig::oneOrNew($data['id'])->set($data);

		// Validate and save the data
		//
		// If save() returns false for any reason, we pass the error
		// message from the model to the CMS for display and fall through
		// to the edit form. We pass the existing model to the edit form
		// so it can repopulate the form with the user-submitted data.
		if (!$model->save())
		{
			Notify::error($model->getError());
			return $this->editTask($model);
		}

		// Redirect back to the main listing with a success message
		Notify::success(Lang::txt('COM_RADIAM_RECORD_SAVED'));

		App::redirect(
			// We pass "false" as a second argument to NOT turn ampersands into their HTML entity (&amp;) in the generated URL
			Route::url('index.php?option=' . $this->_option, false)
		);
	}

	/**
	 * Remove an entry
	 *
	 * @return  void
	 */
	public function deleteTask()
	{
		// Only logged in users!
		if (User::isGuest())
		{
			App::abort(403, Lang::txt('COM_RADIAM_ERROR_UNAUTHORIZED'));
		}

		// [SECURITY] This is a Cross-Site Request Forgery token check
		//
		// This will check if:
		//    1) a CSRF token was passed in a query string
		//    2) the token was valid and tied to the proper user
		//
		// If it fails, it will throw an exception.
		Request::checkToken('get');

		// Incoming data, specifically from POST
		$id = Request::getInt('id', 0);

		// Load the record
		// If no ID was passed or no such record exists, it will throw an error.
		$model = RadConfig::oneOrFail($id);

		// Remove the entry and associated data
		//
		// If the model fails to remove the entry, it will pass
		// an error message. The controller will then redirect to
		// the default task. The CMS detects a message has been
		// set and displays it in the template.
		if (!$model->destroy())
		{
			Notify::error($model->getError());

			App::redirect(
				Route::url('index.php?option=' . $this->_option, false)
			);
		}

		// Set a success message
		Notify::success(Lang::txt('COM_RADIAM_RECORD_DELETED'));

		// Redirect back to the main listing
		App::redirect(
			Route::url('index.php?option=' . $this->_option, false)
		);
	}
}
