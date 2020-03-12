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

if (Pathway::count() <= 0)
{
    Pathway::append(
        Lang::txt('COM_RADIAM'),
        'index.php?option=' . $this->option
    );
}
if ($year = $this->filters['year'])
{
    $title .= ': ' . $year;

    Pathway::append(
        $year,
        'index.php?option=' . $this->option . '&year=' . $year
    );
}
if ($month = $this->filters['month'])
{
    $title .= ': ' . $month;

    Pathway::append(
        sprintf("%02d", $month),
        'index.php?option=' . $this->option . '&year=' . $year . '&month=' . sprintf("%02d", $month)
    );
}

Document::setTitle($title);

$this->css()
     ->js();

?>
<header id="content-header">
    <h2><?php echo Lang::txt('COM_RADIAM'); ?></h2>

    <div id="content-header-extra">
        <?php
        $path  = 'index.php?option=' . $this->option . '&task=feed.rss';
        $path .= ($this->filters['year']) ? '&year=' . $this->filters['year'] : '';
        $path .= ($this->filters['month']) ? '&month=' . $this->filters['month'] : '';

        $feed = Route::url($path);
        if (substr($feed, 0, 4) != 'http')
        {
            $live_site = rtrim(Request::base(),'/');

            $feed = rtrim($live_site, '/') . '/' . ltrim($feed, '/');
        }
        $feed = str_replace('https:://','http://', $feed);
        ?>
        <p><a class="icon-feed feed btn" href="<?php echo $feed; ?>"><?php echo Lang::txt('COM_RADIAM_FEED'); ?></a></p>
    </div>
</header>

<section class="main section">

<?php

    $projects = $this->get('projects');

?>

    <form action="<?php echo Route::url('index.php?option=' . $this->option . '&task=browse'); ?>" method="get" class="section-inner">
        <h3>This is Projects</h3>
        <div class="subject">
            <?php if ($this->getError()) { ?>
                <p class="error"><?php echo $this->getError(); ?></p>
            <?php } ?>
            <div class="container data-entry">
                <input class="entry-search-submit" type="submit" value="<?php echo Lang::txt('COM_RADIAM_SEARCH'); ?>" />
                <fieldset class="entry-search">
                    <legend><?php echo Lang::txt('COM_RADIAM_SEARCH_LEGEND'); ?></legend>
                    <label for="entry-search-field"><?php echo Lang::txt('COM_RADIAM_SEARCH_LABEL'); ?></label>
                    <input type="text" name="search" id="entry-search-field" value="<?php echo $this->escape($this->filters['search']); ?>" placeholder="<?php echo Lang::txt('COM_RADIAM_SEARCH_PLACEHOLDER'); ?>" />
                    <input type="hidden" name="option" value="<?php echo $this->option; ?>" />
                </fieldset>
            </div><!-- / .container -->

            <div class="container">
                <h3>
                    <?php if (isset($this->filters['search']) && $this->filters['search']) { ?>
                        <?php echo Lang::txt('COM_RADIAM_SEARCH_FOR', $this->escape($this->filters['search'])); ?>
                    <?php } else if (!isset($this->filters['year']) || !$this->filters['year']) { ?>
                        <?php echo Lang::txt('COM_RADIAM_LATEST_ENTRIES'); ?>
                    <?php } else {
                        $archiveDate  = $this->filters['year'];
                        $archiveDate .= ($this->filters['month']) ? '-' . $this->filters['month'] : '-01';
                        $archiveDate .= '-01 00:00:00';
                        if ($this->filters['month'])
                        {
                            echo Date::of($archiveDate)->format('M Y');
                        }
                        else
                        {
                            echo Date::of($archiveDate)->format('Y');
                        }
                    } ?>
                </h3>

                <?php var_dump($projects); ?>
                <?php if ($projects && $projects->count > 0) { ?>
                    <?php echo "We've got" . $projects->count ?>
                    <ol class="blog-entries entries">
                    </ol>

                    <?php
                    /* echo $rows
                        ->pagination
                        ->setAdditionalUrlParam('year', $this->filters['year'])
                        ->setAdditionalUrlParam('month', $this->filters['month'])
                        ->setAdditionalUrlParam('search', $this->filters['search']);*/
                    ?>
                <?php } else { ?>
                    <p class="warning"><?php echo Lang::txt('COM_RADIAM_NO_ENTRIES_FOUND'); ?></p>
                <?php } ?>
                <div class="clearfix"></div>
            </div><!-- / .container -->
        </div><!-- / .subject -->

        <aside class="aside">
            <?php if ($this->config->get('access-create-entry')) { ?>
                <p>
                    <a class="icon-add add btn" href="<?php echo Route::url('index.php?option=' . $this->option . '&task=new'); ?>">
                        <?php echo Lang::txt('COM_RADIAM_NEW_ENTRY'); ?>
                    </a>
                </p>
            <?php } ?>

        </aside><!-- / .aside -->
    </form>
</section><!-- / .main section -->
