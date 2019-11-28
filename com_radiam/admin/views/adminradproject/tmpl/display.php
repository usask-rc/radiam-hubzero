<?php
// No direct access
defined('_HZEXEC_') or die();

// Get the permissions helper
$canDo = \Components\Radiam\Helpers\Permissions::getActions('radproject');

// Toolbar is a helper class to simplify the creation of Toolbar 
// titles, buttons, spacers and dividers in the Admin Interface.
//
// Here we'll had the title of the component and various options
// for adding/editing/etc based on if the user has permission to
// perform such actions.
Toolbar::title(Lang::txt('COM_RADIAM') . ': ' . Lang::txt('COM_RADIAM_PROJECTS'));
if ($canDo->get('core.admin'))
{
	Toolbar::preferences($this->option);
	Toolbar::spacer();
}
if ($canDo->get('core.edit.state'))
{
	Toolbar::publishList();
	Toolbar::unpublishList();
	Toolbar::spacer();
}
if ($canDo->get('core.delete'))
{
	Toolbar::deleteList('', 'delete');
}
if ($canDo->get('core.edit'))
{
	Toolbar::editList();
}
if ($canDo->get('core.create'))
{
	Toolbar::addNew();
}
Toolbar::spacer();
Toolbar::help('radconfig');

Html::behavior('framework');
?>

<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" id="adminForm">
	<fieldset id="filter-bar">
		<div class="grid">
			<div class="col span6">
				<label for="filter_search"><?php echo Lang::txt('JSEARCH_FILTER'); ?>:</label>
				<input type="text" name="search" id="filter_search" value="<?php echo $this->escape($this->filters['search']); ?>" placeholder="<?php echo Lang::txt('COM_RADIAM_FILTER_SEARCH_PLACEHOLDER'); ?>" />

				<input type="submit" value="<?php echo Lang::txt('COM_RADIAM_GO'); ?>" />
				<button type="button" onclick="$('#filter_search').val('');$('#filter-state').val('-1');this.form.submit();"><?php echo Lang::txt('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
			<div class="col span6">
				<label for="filter-state"><?php echo Lang::txt('COM_RADIAM_FIELD_CONFIGNAME'); ?>:</label>
				<select name="state" id="filter-state" onchange="this.form.submit();">
					<option value="-1"<?php if ($this->filters['state'] <= 0) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('COM_RADIAM_ALL_STATES'); ?></option>
					<option value="0"<?php if ($this->filters['state'] == 0) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('JUNPUBLISHED'); ?></option>
					<option value="1"<?php if ($this->filters['state'] == 1) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('JPUBLISHED'); ?></option>
					<option value="2"<?php if ($this->filters['state'] == 2) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('JTRASHED'); ?></option>
				</select>
			</div>
		</div>
	</fieldset>

	<table class="adminlist">
		<thead>
			<tr>
				<th><input type="checkbox" name="toggle" value="" onclick="checkAll(<?php echo $this->rows->copy()->total(); ?>);" /></th>
				<th scope="col" class="priority-4"><?php echo Html::grid('sort', 'COM_RADIAM_COL_ID', 'id', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-1"><?php echo Html::grid('sort', 'COM_RADIAM_COL_HZPROJECT', 'state', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-1"><?php echo Html::grid('sort', 'COM_RADIAM_COL_CONFIGNAME', 'state', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="7"><?php echo $this->rows->pagination; ?></td>
			</tr>
		</tfoot>
		<tbody>
		<?php
		$k = 0;
		$i = 0;

		foreach ($this->rows as $row) : ?>
			<tr class="<?php echo "row$k"; ?>">
				<td>
					<input type="checkbox" name="id[]" id="cb<?php echo $i; ?>" value="<?php echo $row->get('id') ?>" onclick="isChecked(this.checked, this);" />
				</td>
				<td class="priority-4">
					<?php echo $row->get('id'); ?>
				</td>
				<td>
					<?php if ($canDo->get('core.edit')) { ?>
						<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=edit&id=' . $row->get('id')); ?>">
							<?php echo $this->escape(stripslashes($row->get('project_id'))); ?>
						</a>
					<?php } else { ?>
						<span>
							<?php echo $this->escape(stripslashes($row->get('project_id'))); ?>
						</span>
					<?php } ?>
				</td>
				<td class="priority-1">
					<?php
					if ($row->get('state') == 1) {
						$alt  = Lang::txt('JPUBLISHED'); $cls  = 'publish'; $task = 'unpublish';
					}
					else if ($row->get('state') == 0) {
						$alt  = Lang::txt('JUNPUBLISHED'); $task = 'publish'; $cls  = 'unpublish';
					}
					else if ($row->get('state') == 2) {
						$alt  = Lang::txt('JTRASHED'); $task = 'publish'; $cls  = 'trash';
					}

					if ($canDo->get('core.edit.state')) { ?>
						<a class="state <?php echo $cls; ?>" href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller . '&task=' . $task . '&id=' . $row->get('id') . '&' . Session::getFormToken() . '=1'); ?>">
							<span><?php echo $alt; ?></span>
						</a>
					<?php } else { ?>
						<span class="state <?php echo $cls; ?>">
							<span><?php echo $alt; ?></span>
						</span>
					<?php } ?>
				</td>
			</tr>
			<?php
			$i++;
			$k = 1 - $k;
		endforeach;
		?>
		</tbody>
	</table>

	<input type="hidden" name="option" value="<?php echo $this->option ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="filter_order" value="<?php echo $this->filters['sort']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->filters['sort_Dir']; ?>" />

	<?php echo Html::input('token'); ?>
</form>
