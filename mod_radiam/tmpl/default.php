<?php
// No direct access
defined('_HZEXEC_') or die();

// Push the module CSS to the template
$this->css();

$projects = $this->projects;

?>
<div id="radiam">
	
	<?php if ($this->projects === null) { ?>
		<ul class="module-nav">
			<li>
				<a class="icon-login" href="<?php echo Route::url('index.php?option=com_radiam&task=login'); ?>">
					<?php echo Lang::txt('MOD_RADIAM_LOGIN'); ?>
				</a>
			</li>
		</ul>
		<p>
			<?php echo Lang::txt('MOD_RADIAM_LOGIN_FIRST'); ?>
		</p>
	<?php } 
		elseif ($this->total == 0) {?>
		<ul class="module-nav">
			<li>
				<?php echo Lang::txt('MOD_RADIAM_NO_PROJECTS'); ?>
			</li>
		</ul>
	<?php } 
		else {?>
		<ul class="module-nav">
			<li>
				<?php echo Lang::txt('MOD_RADIAM_LIST_PROJECTS'); ?>
			</li>
		</ul>
	<?php } ?>
	<?php if ($projects && $this->total > 0) { ?>
		<ul class="compactlist">
			<?php
			$i = 0;
			foreach ($projects as $project) {
				if ($i >= $this->limit) {
					break;
				}
				$goto = 'project=' . $project->id;
				$i++;
				?>
					<li>
						<a href="<?php echo Route::url('index.php?option=com_radiam&task=display?' . $goto); ?>" title="<?php echo $this->escape($project->name); ?>"><?php echo \Hubzero\Utility\Str::truncate($this->escape($project->name), 30); ?></a>
					</li>
				<?php
			}
			?>
		</ul>	
	<?php } ?>

	<?php if ($this->total > $this->limit) { ?>
		<p class="note">
			<?php echo Lang::txt('MOD_RADIAM_YOU_HAVE_MORE', $this->limit, $this->total, Route::url('index.php?option=com_radiam&task=display')); ?>
		</p>
	<?php } ?>
</div>