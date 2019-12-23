<?php
// No direct access
defined('_HZEXEC_') or die();

// Get the permissions helper
$canDo = \Components\Radiam\Helpers\Permissions::getActions('radconfig');

// Toolbar is a helper class to simplify the creation of Toolbar 
// titles, buttons, spacers and dividers in the Admin Interface.
//
Toolbar::title(Lang::txt('COM_RADIAM') . ': ' . Lang::txt('COM_RADIAM_RADCONFIG'));
if ($canDo->get('core.admin'))
{
	Toolbar::preferences($this->option);
	Toolbar::spacer();
}
Toolbar::spacer();
Toolbar::help('adminradmain');

Html::behavior('framework');
?>


<div style="padding-left:30px"><h2><a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradconfig&task=display'); ?>"><?php echo Lang::txt('COM_RADIAM_SETTINGS'); ?></a></h2>
<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradconfig&task=add'); ?>" data-title="New" data-task="adminradconfig"><span class="icon-new icon-32-new">Add New</span></a>
</div>
<table class="adminlist">
	<thead>
		<tr>
			<th scope="col" class="priority-4"><?php echo Lang::txt('COM_RADIAM_COL_ID'); ?></th>
			<th scope="col" class="priority-1"><?php echo Lang::txt('COM_RADIAM_COL_CONFIGNAME'); ?></th>
			<th scope="col" class="priority-1"><?php echo Lang::txt('COM_RADIAM_COL_CONFIGVALUE'); ?></th>
			<th scope="col" class="priority-1"><?php echo Lang::txt('COM_RADIAM_COL_STATE'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php
	$k = 0; $i = 0;
	foreach ($this->configs as $row) : ?>
		<tr class="<?php echo "row$k"; ?>">
			<td class="priority-4">
				<?php echo $row->get('id'); ?>
			</td>
			<td>
				<?php if ($canDo->get('core.edit')) { ?>
					<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradconfig&task=edit&id=' . $row->get('id')); ?>">
						<?php echo $this->escape(stripslashes($row->get('configname'))); ?>
					</a>
				<?php } else { ?>
					<span><?php echo $this->escape(stripslashes($row->get('configname'))); ?></span>
				<?php } ?>
			</td>
			<td class="priority-4">
				<span><?php echo $this->escape(stripslashes($row->get('configvalue'))); ?></span>
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
				?>
				<span class="state <?php echo $cls; ?>"><span><?php echo $alt; ?></span></span>
			</td>
		</tr>
		<?php
		$i++; $k = 1 - $k;
	endforeach;
	?>
	</tbody>
</table>

<div style="padding-left:30px"><h2><a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradproject&task=display'); ?>"><?php echo Lang::txt('COM_RADIAM_PROJECTS'); ?></a></h2>
<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradproject&task=add'); ?>"><span class="icon-new icon-32-new">Add New</span></a>
</div>
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
				<label for="filter-state"><?php echo Lang::txt('COM_RADIAM_FIELD_STATE'); ?>:</label>
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
				<th scope="col" class="priority-1"><?php echo Html::grid('sort', 'COM_RADIAM_COL_ID', 'id', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-4"><?php echo Html::grid('sort', 'COM_RADIAM_COL_HZPROJECT', 'project_id', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-4"><?php echo Html::grid('sort', 'COM_RADIAM_COL_RADPROJECT', 'radiam_project_uuid', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
				<th scope="col" class="priority-1"><?php echo Html::grid('sort', 'COM_RADIAM_COL_STATE', 'state', @$this->filters['sort_Dir'], @$this->filters['sort']); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="7"><?php echo $this->rows->pagination; ?></td>
			</tr>
		</tfoot>
		<tbody>
		<?php
		$k = 0; $i = 0;
		foreach ($this->rows as $row) : ?>
			<tr class="<?php echo "row$k"; ?>">
				<td>
					<?php echo $row->get('id'); ?>
				</td>
				<td>
					<?php if ($canDo->get('core.edit')) { ?>
						<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradproject&task=edit&id=' . $row->get('id')); ?>">
							<?php echo $this->escape(stripslashes($row->project->get('title'))); ?>
						</a>
					<?php } else { ?>
						<span><?php echo $this->escape(stripslashes($row->project->get('title'))); ?></span>
					<?php } ?>
				</td>
				<td>
					<?php if ($canDo->get('core.edit')) { ?>
						<a href="<?php echo Route::url('index.php?option=' . $this->option . '&controller=adminradproject&task=edit&id=' . $row->get('id')); ?>">
							<?php echo $this->escape(stripslashes($row->get('radiam_project_uuid'))); ?>
						</a>
					<?php } else { ?>
						<span><?php echo $this->escape(stripslashes($row->get('radiam_project_uuid'))); ?></span>
					<?php } ?>
				</td>
				<td>
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
					?>
					<span class="state <?php echo $cls; ?>"><span><?php echo $alt; ?></span></span>
				</td>
			</tr>
			<?php
			$i++; $k = 1 - $k;
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
