<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Shawn Rice <zooley@purdue.edu>
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();


$title = Lang::txt('COM_RADIAM');

$project = $this->filters['project'];
$projects = $this->get('projects');
$files = $this->get('files');
$noFilesInProject = $projects->count >= 1 && $files->count == 0 && ($this->filters["search"] == "");
$noFilesInSearch = $projects->count >= 1 && $files->count == 0 && !($this->filters["search"] == "");

if (Pathway::count() <= 0)
{
    Pathway::append(
        Lang::txt('COM_RADIAM'),
        'index.php?option=' . $this->option
    );
}

Document::setTitle($title);

$this->css()
     ->js();

?>

<header id="content-header">
    <h2><?php echo Lang::txt('COM_RADIAM'); ?></h2>

    <div id="content-header-extra">
        <span><?php echo Lang::txt('CA_RADIAM_PROJECT'); ?></span>
        <?php echo $projects->renderSelect($project); ?>
    </div>
</header>

<section class="main section">

<?php

function formatBytes($bytes, $decimals) {
    if($bytes == 0) return '0&nbsp;KB';
    $k = 1024;
    if (!$decimals) {
        $decimals = 2;
    }
    $sizes = ['&nbsp;&nbsp;&nbsp;B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $i = floor(log($bytes) / log($k));
    // Skip bytes and do KB instead
    if ($i === 0) {
        $i = 1;
    }
    return number_format($bytes / pow($k, $i), $decimals) . ' ' . $sizes[$i];
}

?>
    <form action="<?php echo Route::url('index.php?option=' . $this->option . '&task=browse'); ?>" method="get" class="section-inner">
        <div class="subject">
            <?php if ($this->getError()) { ?>
                <p class="error"><?php echo $this->getError(); ?></p>
            <?php } ?>
            <?php if (!$noFilesInProject) { ?>
                <div class="container data-entry">
                    <input class="entry-search-submit" type="submit" value="<?php echo Lang::txt('COM_RADIAM_SEARCH'); ?>" />
                    <fieldset class="entry-search">
                        <legend><?php echo Lang::txt('COM_RADIAM_SEARCH_LEGEND'); ?></legend>
                        <label for="entry-search-field"><?php echo Lang::txt('COM_RADIAM_SEARCH_LABEL'); ?></label>
                        <input type="text" name="search" id="entry-search-field" value="<?php echo $this->escape($this->filters['search']); ?>" placeholder="<?php echo Lang::txt('COM_RADIAM_SEARCH_PLACEHOLDER'); ?>" />
                        <input type="hidden" name="option" value="<?php echo $this->option; ?>" />
                        <input type="hidden" name="project" value="<?php echo $project; ?>" />
                    </fieldset>
                </div><!-- / .container -->
            <?php } ?>

            <div>
                <h3>
                    <?php if (isset($this->filters['search']) && $this->filters['search']) { ?>
                        <?php echo Lang::txt('COM_RADIAM_SEARCH_FOR', $this->escape($this->filters['search'])); ?>
                    <?php } ?>
                </h3>

                <?php if ($projects->count == 0) { ?>
                    <p class="warning"><?php echo Lang::txt('CA_RADIAM_NO_PROJECTS_FOUND'); ?></p>
                <?php } else if ($noFilesInSearch) { ?>
                    <p class="warning"><?php echo Lang::txt('CA_RADIAM_NO_FILES_FOUND_SEARCH'); ?></p>
                <?php } else if ($noFilesInProject) { ?>
                    <p class="warning"><?php echo Lang::txt('CA_RADIAM_NO_FILES_FOUND'); ?></p>
                <?php } else if ($projects->count >= 1 && $files->count > 0) { ?>
                    <div class="headings">
                        <div class="row">
                            <div class="col-sm-8 col-md-8 col-lg-8">
                                <?php echo Lang::txt('CA_RADIAM_FILENAME'); ?>
                            </div>
                            <div class="col-sm-1 col-md-1 col-lg-1">
                                <?php echo Lang::txt('CA_RADIAM_SIZE'); ?>
                            </div>
                            <div class="col-sm-1 col-md-1 col-lg-1">
                                <?php echo Lang::txt('CA_RADIAM_AGENT'); ?>
                            </div>
                            <div class="col-sm-1 col-md-1 col-lg-1">
                                <?php echo Lang::txt('CA_RADIAM_LOCATION'); ?>
                            </div>
                            <div class="show-all col-sm-1 col-md-1 col-lg-1">
                                <div class="btn btn-primary"><?php echo Lang::txt('CA_RADIAM_MORE_ALL'); ?></div>
                            </div>
                            <div class="hide-all col-sm-1 col-md-1 col-lg-1">
                                <div class="btn btn-secondary"><?php echo Lang::txt('CA_RADIAM_LESS_ALL'); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php
                        $cls = 'even';
                        foreach ($files->files as $file) {
                            $cls = ($cls == 'even') ? 'odd' : 'even';
                            echo $file->render($cls);
                        } ?>
                    <div class="files-footer"></div>
                <?php } ?>

                <?php if (!$noFilesInProject) {
                    echo $this->pagination->render();
                }?>

                <div class="clearfix"></div>
            </div><!-- / .container -->
        </div><!-- / .subject -->

        <!--aside class="aside">

        </aside--><!-- / .aside -->

    </form>
</section><!-- / .main section -->

    <script src="/app/components/com_radiam/site/assets/js/bootstrap.bundle.min.js" type="text/javascript"></script>
    <link rel="stylesheet" href="/app/components/com_radiam/site/assets/css/bootstrap.min.css" type="text/css">
