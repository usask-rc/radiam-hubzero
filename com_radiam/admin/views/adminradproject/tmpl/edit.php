<?php
// No direct access
defined('_HZEXEC_') or die();

// Get the permissions helper
$canDo = \Components\Radiam\Helpers\Permissions::getActions('radproject');

// Toolbar is a helper class to simplify the creation of Toolbar 
// titles, buttons, spacers and dividers in the Admin Interface.
//
// Here we'll had the title of the component and options
// for saving based on if the user has permission to
// perform such actions. Everyone gets a cancel button.
$text = ($this->task == 'edit' ? Lang::txt('JACTION_EDIT') : Lang::txt('JACTION_CREATE'));

Toolbar::title(Lang::txt('COM_RADIAM') . ': ' . Lang::txt('COM_RADIAM_PROJECTS') . ': ' . $text);
if ($canDo->get('core.edit'))
{
	Toolbar::apply();
	Toolbar::save();
	Toolbar::spacer();
}
Toolbar::cancel();
Toolbar::spacer();
Toolbar::help('adminradmain');
?>
<script type="text/javascript">
Joomla.submitbutton = function(pressbutton) {
	var form = document.adminForm;

	if (pressbutton == 'cancel') {
		Joomla.submitform(pressbutton, document.getElementById('item-form'));
		return;
	}

	// do field validation
	if ($('#field-hzproject').val() == ''){
		alert("<?php echo Lang::txt('COM_RADIAM_ERROR_MISSING_HZPROJECT'); ?>");
	} else {
		Joomla.submitform(pressbutton, document.getElementById('item-form'));
	}
	if ($('#field-configvalue').val() == ''){
		alert("<?php echo Lang::txt('COM_RADIAM_ERROR_MISSING_RADPROJECT'); ?>");
	} else {
		Joomla.submitform(pressbutton, document.getElementById('item-form'));
	}
}
</script>
<?php
	echo "hello";
	echo $this->row->get('state');
?>
<form action="<?php echo Route::url('index.php?option=' . $this->option . '&controller=' . $this->controller); ?>" method="post" name="adminForm" class="editform" id="item-form">
	<div class="grid">
		<div class="col span7">
			<fieldset class="adminform">
				<legend><span><?php echo Lang::txt('JDETAILS'); ?></span></legend>

				<div class="input-wrap">
					<label for="project_id"><?php echo Lang::txt('COM_RADIAM_FIELD_HZPROJECT'); ?> <span class="required"><?php echo Lang::txt('JOPTION_REQUIRED'); ?></span></label>
					<select name="fields[project_id]" id="field-project_id">
						<?php foreach ($this->hubzero_project as $project) : ?>
							<?php $sel = ($project->id == $this->row->project_id) ? 'selected="selected"' : ''; ?>
							<option <?php echo $sel; ?> value="<?php echo $project->id; ?>"><?php echo $this->escape($project->title); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
		
				<div class="input-wrap">
					<label for="radiam_project_info"><?php echo Lang::txt('COM_RADIAM_FIELD_RADPROJECT'); ?> <span class="required"><?php echo Lang::txt('JOPTION_REQUIRED'); ?></span></label>
					<select name="fields[radiam_project_info]" id="field-radiam_project_info">
					<?php foreach ($this->radiam_project as $rad_project) : ?>
							<?php $sel = ($rad_project->id == $this->row->radiam_project_uuid) ? 'selected="selected"' : ''; ?>
							<option <?php echo $sel; ?> value="<?php echo $rad_project->id; ?>,<?php echo $rad_project->name; ?>">
								<?php echo $this->escape($rad_project->name); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<input type="hidden" name="fields[radiam_project_name]" id="field-radiam_project_name" value="<?php echo $this->escape($rad_project->name); ?>" />


			</fieldset>
		</div>
		<div class="col span5">
			<table class="meta">
				<tbody>
					<tr>
						<th><?php echo Lang::txt('COM_RADIAM_FIELD_ID'); ?>:</th>
						<td>
							<?php echo $this->row->get('id', 0); ?>
							<input type="hidden" name="fields[id]" value="<?php echo $this->row->get('id'); ?>" />
						</td>
					</tr>
					<?php if ($this->row->get('id')) { ?>
						<tr>
							<th><?php echo Lang::txt('COM_RADIAM_FIELD_CREATOR'); ?>:</th>
							<td>
								<?php
								$editor = User::getInstance($this->row->get('created_by'));
								echo $this->escape(stripslashes($editor->get('name')));
								?>
								<input type="hidden" name="fields[created_by]" id="field-created_by" value="<?php echo $this->escape($this->row->get('created_by')); ?>" />
							</td>
						</tr>
						<tr>
							<th><?php echo Lang::txt('COM_RADIAM_FIELD_CREATED'); ?>:</th>
							<td>
								<?php echo $this->row->get('created'); ?>
								<input type="hidden" name="fields[created]" id="field-created" value="<?php echo $this->escape($this->row->get('created')); ?>" />
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<fieldset class="adminform">
				<legend><span><?php echo Lang::txt('JGLOBAL_FIELDSET_PUBLISHING'); ?></span></legend>

				<div class="input-wrap">
					<label for="field-state"><?php echo Lang::txt('COM_RADIAM_FIELD_STATE'); ?>:</label>
					<select name="fields[state]" id="field-state">
						<option value="1"<?php if ($this->row->get('state') == 1) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('JPUBLISHED'); ?></option>
						<option value="0"<?php if ($this->row->get('state') == 0) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('JUNPUBLISHED'); ?></option>
						<option value="2"<?php if ($this->row->get('state') == 2) { echo ' selected="selected"'; } ?>><?php echo Lang::txt('JTRASHED'); ?></option>
					</select>
				</div>
			</fieldset>
		</div>
	</div>

	<input type="hidden" name="option" value="<?php echo $this->option; ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="task" value="save" />

	<?php echo Html::input('token'); ?>
</form>