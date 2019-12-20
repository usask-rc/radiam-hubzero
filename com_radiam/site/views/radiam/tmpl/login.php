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
/*
 *
TODO Does the login page need saved filters such as project?
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
}*/

Document::setTitle($title);

$this->css()
     ->js();

?>
<header id="content-header">
    <h2><?php echo Lang::txt('COM_RADIAM'); ?></h2>
</header>

<section class="main section">


    <form action="<?php echo Route::url('index.php?option=' . $this->option . '&task=login'); ?>" method="post" class="section-inner">
        <div class="subject">
            <?php if ($this->getError()) { ?>
                <p class="error"><?php echo $this->getError(); ?></p>
            <?php } ?>
            <?php if (User::isGuest()) { ?>
                <div>
                    <?php echo Lang::txt('COM_RADIAM_NEED_USER'); ?>
                </div>
            <?php } else { ?>
                <h3><?php echo Lang::txt('COM_RADIAM_LOGIN_HEADING'); ?></h3>
                <div>
                    <?php echo Lang::txt('COM_RADIAM_USER_LABEL'); ?>
                    <input type="text" name="username" required>
                </div>
                </br>
                <div>
                    <?php echo Lang::txt('COM_RADIAM_PASSWORD_LABEL'); ?>
                    <input type="password" name="passwd" class="passwd" required></div></br>
                <input class="login-submit btn btn-primary" type="submit" value="<?php echo Lang::txt('COM_RADIAM_SUBMIT'); ?>">


            <?php } ?>
                <div class="clearfix"></div>
            </div><!-- / .container -->
        </div><!-- / .subject -->

    </form>
</section><!-- / .main section -->
