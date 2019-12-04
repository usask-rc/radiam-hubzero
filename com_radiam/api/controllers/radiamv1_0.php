<?php
namespace Components\Radiam\Api\Controllers;

use Components\Radiam\Models\RadConfig;
use Hubzero\Component\ApiController;
use Hubzero\Utility\Date;
use Exception;
use stdClass;
use Request;
use Route;
use Lang;
use App;

require_once dirname(dirname(__DIR__)) . DS . 'models' . DS . 'radconfig.php';

/**
 * API controller class for radconfig
 */
class Radiamv1_0 extends ApiController
{
	/**
	 * Display a list of entries
	 *
	 * @apiMethod GET
	 * @apiUri    /radiam/radconfig/list
	 * @apiParameter {
	 * 		"name":          "limit",
	 * 		"description":   "Number of result to return.",
	 * 		"type":          "integer",
	 * 		"required":      false,
	 * 		"default":       25
	 * }
	 * @apiParameter {
	 * 		"name":          "start",
	 * 		"description":   "Number of where to start returning results.",
	 * 		"type":          "integer",
	 * 		"required":      false,
	 * 		"default":       0
	 * }
	 * @apiParameter {
	 * 		"name":          "sort",
	 * 		"description":   "Field to sort results by.",
	 * 		"type":          "string",
	 * 		"required":      false,
	 *      "default":       "created",
	 * 		"allowedValues": "created, configname, configvalue, id, state"
	 * }
	 * @apiParameter {
	 * 		"name":          "sort_Dir",
	 * 		"description":   "Direction to sort results by.",
	 * 		"type":          "string",
	 * 		"required":      false,
	 * 		"default":       "desc",
	 * 		"allowedValues": "asc, desc"
	 * }
	 * @return  void
	 */
	public function listTask()
	{
		$query = RadConfig::all()
			->whereEquals('state', 1);

		$response = new stdClass;
		$response->total = with(clone $query)->total();

		if ($limit = Request::getInt('limit', 20))
		{
			$query->limit($limit);
		}
		if ($start = Request::getInt('limitstart', 0))
		{
			$query->start($start);
		}
		if (($orderby  = Request::getWord('sort', 'id'))
		 && ($orderdir = Request::getWord('sortDir', 'ASC')))
		{
			$query->order($orderby, $orderdir);
		}

		$response->records = $query->rows()->toObject();

		if (count($response->records) > 0)
		{
			foreach ($response->records as $i => $entry)
			{
				$response->records[$i]->uri = Route::url('index.php?option=' . $this->_option . '&task=view&id=' . $entry->id);
			}
		}

		$response->success = true;

		$this->send($response);
	}

	/**
	 * Create an entry
	 *
	 * @apiMethod POST
	 * @apiUri    /radiam/radconfig
	 * @apiParameter {
	 * 		"name":        "created",
	 * 		"description": "Created timestamp (YYYY-MM-DD HH:mm:ss)",
	 * 		"type":        "string",
	 * 		"required":    false,
	 * 		"default":     "now"
	 * }
	 * @apiParameter {
	 * 		"name":        "crated_by",
	 * 		"description": "User ID of entry creator",
	 * 		"type":        "integer",
	 * 		"required":    false,
	 * 		"default":     0
	 * }
	 * @apiParameter {
	 * 		"name":        "state",
	 * 		"description": "Published state (0 = unpublished, 1 = published)",
	 * 		"type":        "integer",
	 * 		"required":    false,
	 * 		"default":     0
	 * }
	 * @return    void
	 */
	public function createTask()
	{
		$this->requiresAuthentication();
		$this->authorizeOrFail();

		$fields = array(
			'created'        => Request::getVar('created', with(new Date('now'))->toSql(), 'post'),
			'created_by'     => Request::getInt('created_by', 0, 'post'),
			'state'          => Request::getInt('state', 0, 'post')
		);

		// Create object and store content
		$record = RadConfig::blank()->set($fields);

		// Do the actual save
		if (!$record->save())
		{
			App::abort(500, Lang::txt('COM_RADIAM_ERROR_RECORD_CREATE_FAILED'));
		}

		$this->send($record, 201);
	}

	/**
	 * Retrieve an entry
	 *
	 * @apiMethod GET
	 * @apiUri    /radiam/radconfig/{id}
	 * @apiParameter {
	 * 		"name":        "id",
	 * 		"description": "Entry identifier",
	 * 		"type":        "integer",
	 * 		"required":    true,
	 * 		"default":     null
	 * }
	 * @return    void
	 */
	public function readTask()
	{
		$id = Request::getInt('id', 0);

		// Error checking
		if (empty($id))
		{
			App::abort(404, Lang::txt('COM_RADIAM_ERROR_MISSING_ID'));
		}

		try
		{
			$record = RadConfig::oneOrFail($id);
		}
		catch (Hubzero\Error\Exception\RuntimeException $e)
		{
			App::abort(404, Lang::txt('COM_RADIAM_ERROR_RECORD_NOT_FOUND'));
		}

		$row = $record->toObject();
		$row->uri = Route::url($record->link());

		$this->send($row);
	}

	/**
	 * Update an entry
	 *
	 * @apiMethod PUT
	 * @apiUri    /radiam/radconfig/{id}
	 * @apiParameter {
	 * 		"name":        "id",
	 * 		"description": "Entry identifier",
	 * 		"type":        "integer",
	 * 		"required":    true,
	 * 		"default":     null
	 * }
	 * @apiParameter {
	 * 		"name":        "created",
	 * 		"description": "Created timestamp (YYYY-MM-DD HH:mm:ss)",
	 * 		"type":        "string",
	 * 		"required":    false,
	 * 		"default":     "now"
	 * }
	 * @apiParameter {
	 * 		"name":        "crated_by",
	 * 		"description": "User ID of entry creator",
	 * 		"type":        "integer",
	 * 		"required":    false,
	 * 		"default":     0
	 * }
	 * @apiParameter {
	 * 		"name":        "state",
	 * 		"description": "Published state (0 = unpublished, 1 = published)",
	 * 		"type":        "integer",
	 * 		"required":    false,
	 * 		"default":     0
	 * }
	 * @return    void
	 */
	public function updateTask()
	{
		$this->requiresAuthentication();
		$this->authorizeOrFail();

		$id = Request::getInt('id');

		if (!$id)
		{
			App::abort(404, Lang::txt('COM_RADIAM_ERROR_MISSING_ID'));
		}

		$fields = array(
			'created'        => Request::getVar('created', with(new Date('now'))->toSql(), 'post'),
			'created_by'     => Request::getInt('created_by', 0, 'post'),
			'state'          => Request::getInt('state', 0, 'post')
		);

		// Create object and store content
		$record = RadConfig::oneOrFail($id)->set($fields);

		// Do the actual save
		if (!$record->save())
		{
			App::abort(500, Lang::txt('COM_RADIAM_ERROR_RECORD_UPDATE_FAILED'));
		}

		$this->send($record, 201);
	}

	/**
	 * Delete an entry
	 *
	 * @apiMethod DELETE
	 * @apiUri    /radiam/radconfig/{id}
	 * @apiParameter {
	 * 		"name":        "id",
	 * 		"description": "Entry identifier",
	 * 		"type":        "integer",
	 * 		"required":    true,
	 * 		"default":     null
	 * }
	 * @return    void
	 */
	public function deleteTask()
	{
		$this->requiresAuthentication();
		$this->authorizeOrFail();

		$id = Request::getInt('id');

		if (!$id)
		{
			App::abort(404, Lang::txt('COM_RADIAM_ERROR_MISSING_ID'));
		}

		// Create object and store content
		$record = RadConfig::oneOrFail($id);

		// Do the actual save
		if (!$record->destroy())
		{
			App::abort(500, Lang::txt('COM_RADIAM_ERROR_RECORD_DELETE_FAILED'));
		}

		$this->send(null, 204);
	}

	/**
	 * Checks to ensure appropriate authorization
	 *
	 * @return  bool
	 * @throws  Exception
	 */
	private function authorizeOrFail()
	{
		// Make sure action can be performed
		if (!User::authorise('core.manage', $this->_option))
		{
			App::abort(401, Lang::txt('COM_RADIAM_ERROR_UNAUTHORIZED'));
		}

		return true;
	}
}
