<?php
// No direct access
defined('_HZEXEC_') or die();
?>
<div class="<?php echo $this->params->get('cls', 'mod_radiam'); ?>">
	<?php echo Lang::txt('MOD_RADIAM_RANDOM_USERS'); ?>

	<ul>
		<?php foreach ($items as $item) { ?>
			<li>
				<?php echo Lang::txt('MOD_RADIAM_USER_LABEL', $item->name); ?>
			</li>
		<?php } ?>
	</ul>
</div>