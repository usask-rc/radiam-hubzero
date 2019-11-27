<?php
namespace Components\Radiam\Models;

use Hubzero\Database\Relational;
use Session;
use Lang;
use Date;

/**
 * Radiam model class for a radproject
 */
class RadProject extends Relational
{
	/**
	 * The table namespace
	 *
	 * @var  string
	 **/
	protected $namespace = 'radiam';

	/**
	 * Default order by for model
	 *
	 * @var  string
	 */
	public $orderBy = 'id';

	/**
	 * Fields and their validation criteria
	 *
	 * @var  array
	 */
	protected $rules = array(
		'project_id' => 'notempty',
		'radiam_project_uuid' => 'notempty',
		'radiam_user_uuid' => 'notempty'
	);

	/**
	 * Automatically fillable fields
	 *
	 * @var  array
	 **/
	public $always = array(
	);

	/**
	 * Defines a belongs to one relationship between task and assignee
	 *
	 * @return  object
	 */
	public function creator()
	{
		return $this->belongsToOne('Hubzero\User\User', 'created_by');
	}

	/**
	 * Return a formatted timestamp for the 
	 * created date
	 *
	 * @param   string  $as  What format to return
	 * @return  string
	 */
	public function created($as='')
	{
		return $this->_datetime('created', $as);
	}

	/**
	 * Return a formatted timestamp
	 *
	 * @param   string  $field  Datetime field to use [premiere_date, finale_date, created]
	 * @param   string  $as     What format to return
	 * @return  string
	 */
	protected function _datetime($field, $as='')
	{
		$as = strtolower($as);
		$dt = $this->get($field);

		if ($as)
		{
			if ($as == 'date')
			{
				$dt = Date::of($dt)->toLocal(Lang::txt('DATE_FORMAT_HZ1'));
			}
			else if ($as == 'time')
			{
				$dt = Date::of($dt)->toLocal(Lang::txt('TIME_FORMAT_HZ1'));
			}
			else if ($as == 'relative')
			{
				$dt = Date::of($dt)->relative();
			}
			else
			{
				$dt = Date::of($dt)->toLocal($as);
			}
		}

		return $dt;
	}

	/**
	 * Generate and return various links to the entry
	 * Link will vary depending upon action desired, such as edit, delete, etc.
	 *
	 * @param   string  $type  The type of link to return
	 * @return  string
	 */
	public function link($type='')
	{
		static $base;

		if (!isset($base))
		{
			$base = 'index.php?option=com_radiam';
		}

		$link = $base;

		// If it doesn't exist or isn't published
		switch (strtolower($type))
		{
			case 'edit':
				$link .= '&task=edit&id=' . $this->get('id');
			break;

			case 'delete':
				$link .= '&task=delete&id=' . $this->get('id') . '&' . Session::getFormToken() . '=1';
			break;

			case 'view':
			case 'permalink':
			default:
				$link .= '&task=view&id=' . $this->get('id');
			break;
		}

		return $link;
	}
}
