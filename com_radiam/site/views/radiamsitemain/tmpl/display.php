<?php
// Push CSS to the document
//
// The css() method provides a quick and convenient way to attach stylesheets. 
// 
// 1. The name of the stylesheet to be pushed to the document (file extension is optional). 
//    If no name is provided, the name of the component or plugin will be used. For instance, 
//    if called within a view of the component com_tags, the system will look for a stylesheet named tags.css.
// 
// 2. The name of the extension to look for the stylesheet. For components, this will be 
//    the component name (e.g., com_tags). For plugins, this is the name of the plugin folder 
//    and requires the third argument be passed to the method.
//
// Method chaining is also allowed.
// $this->css()  
//      ->css('another');

$this->css();

// Similarly, a js() method is available for pushing javascript assets to the document.
// The arguments accepted are the same as the css() method described above.
//
// $this->js();

// Set the document title
//
// This sets the <title> tag of the document and will overwrite any previous
// title set. To append or modify an existing title, it must be retrieved first
// with $title = Document::getTitle();
Document::setTitle(Lang::txt('COM_RADIAM') . ': ' . Lang::txt('COM_RADIAM_RADCONFIG'));

// Set the pathway (breadcrumbs)
//
// Breadcrumbs are displayed via a breadcrumbs module and may or may not be enabled for
// all hubs and/or templates. In general, it's good practice to set the pathway
// even if it's unknown if hey will be displayed or not.
Pathway::append(
	Lang::txt('COM_RADIAM'),  // Text to display
	'index.php?option=' . $this->option  // Link. Route::url() not needed.
);
?>
<header id="content-header">
	<h2><?php echo Lang::txt('COM_RADIAM'); ?>: <?php echo Lang::txt('COM_RADIAM_RADCONFIG'); ?></h2>

	<?php if (User::authorise('core.create', $this->option)) { ?>
		<div id="content-header-extra">
			<p>
				<a class="icon-add btn" href="<?php echo Route::url('index.php?option=' . $this->option . '&task=add'); ?>">
					<?php echo Lang::txt('COM_RADIAM_NEW'); ?>
				</a>
			</p>
		</div>
	<?php } ?>
</header>

<section class="main section">
	<form class="section-inner" action="<?php echo Route::url('index.php?option=' . $this->option); ?>" method="get">
		<div class="subject">
			<table class="entries">
				<caption><?php echo Lang::txt('COM_RADIAM_RADCONFIG'); ?></caption>
				<thead>
					<tr>
						<th><?php echo Lang::txt('COM_RADIAM_COL_ID'); ?></th>
						<th><?php echo Lang::txt('COM_RADIAM_COL_CONFIGNAME'); ?></th>
						<th><?php echo Lang::txt('COM_RADIAM_COL_CONFIGVALUE'); ?></th>
						<?php if (User::authorise('core.edit', $this->option)) { ?>
							<th></th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($this->records as $record) { ?>
						<tr>
							<th>
								<?php echo $this->escape($record->get('id')); ?>
							</th>
							<td>
								<a href="<?php echo Route::url($record->link()); ?>">
									<?php echo $this->escape($record->get('configname')); ?>
								</a>
							</td>
							<td>
								<a href="<?php echo Route::url($record->link()); ?>">
									<?php echo $this->escape($record->get('configvalue')); ?>
								</a>
							</td>
							<?php if (User::authorise('core.edit', $this->option)) { ?>
								<td>
									<a class="icon-edit btn" href="<?php echo Route::url($record->link('edit')); ?>">
										<?php echo Lang::txt('JACTION_EDIT'); ?>
									</a>
									<a class="icon-delete btn" href="<?php echo Route::url($record->link('delete')); ?>">
										<?php echo Lang::txt('JACTION_DELETE'); ?>
									</a>
								</td>
							<?php } ?>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<?php 
			echo $this->records->pagination;
			?>
		</div>
	</form>
</section>