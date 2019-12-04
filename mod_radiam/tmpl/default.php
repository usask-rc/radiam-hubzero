<?php
// No direct access
defined('_HZEXEC_') or die();
?>
<div class="<?php echo $this->params->get('projectcount', 'mod_radiam'); ?>">
	<?php echo Lang::txt('MOD_RADIAM_MOD_TITLE'); ?>

	<ul>
		<?php foreach ($projects as $project) { ?>
			<li>
				<?php echo $project->radiam_project_uuid; ?>
			</li>
		<?php } ?>
	</ul>
</div>