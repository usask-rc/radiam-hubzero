<?php

namespace Components\Radiam\Models;

use Hubzero\Utility\String;
use Hubzero\Config\Registry;
use Hubzero\Form\Form;
use Filesystem;
use Component;
use Lang;
use User;
use Date;

class File
{
    public $id = "";
    public $locationId = "";
    public $locationName = "";
    public $extension = "";
    public $lastModified = "";
    public $lastChange = "";
    public $name = "";
    public $group = "";
    public $path = "";
    public $agent = "";
    public $indexedBy = "";
    public $indexingDate = "";
    public $owner = "";
    public $filesize = "";
    public $pathParent = "";
    public $lastAccess = "";
    public $indexedDate = "";
    public $type = "";
    public $pathParentKeyword = "";
    public $pathKeyword = "";
    public $pathAgnostic = "";
    public $pathAgnosticKeyword = "";
    public $entity = "";


    function __construct($json, $locationName) {
        $this->id = $json->id;
        $this->locationId = $json->location;
        $this->locationName = $locationName;
        $this->extension = isset($json->extension) ? $json->extension : null;
        $this->lastModified = isset($json->last_modified) ? $json->last_modified : $json->date_modified;
        $this->lastChange = isset($json->last_change) ? $json->last_change : null; 
        $this->name = $json->name;
        $this->group = isset($json->group) ? $json->group : null;
        $this->path = $json->path;
        $this->agent = $json->agent;
        $this->indexedBy = isset($json->indexed_by) ? $json->indexed_by : null;
        $this->indexingDate = isset($json->indexing_date) ? $json->indexing_date : null;
        $this->owner = isset($json->owner) ? $json->owner : null;
        $this->filesize = isset($json->filesize) ? $json->filesize : null;
        $this->pathParent = isset($json->path_parent) ? $json->path_parent : null;
        $this->lastAccess = isset($json->last_access) ? $json->last_access : null;
        $this->indexedDate = $json->indexed_date;
        $this->type = $json->type;
        $this->pathParentKeyword = isset($json->path_parent_keyword) ? $json->path_parent_keyword : null;
        $this->pathKeyword = $json->path_keyword;
        $this->pathAgnostic = $json->path_agnostic;
        $this->pathAgnosticKeyword = $json->path_agnostic_keyword;
        $this->entity = $json->entity;
    }

    function renderMain($value, $classes) {
        ob_start();
        ?>
        <div class="<?php echo $classes ?>">
            <?php echo $value ?>
        </div>
        <?php
        return ob_get_clean();
    }

    function renderExtra($label, $value) {
        ob_start();
        ?>
            <div class="row">
                <div class="label col-sm-2 col-md-2 col-lg-2">
                    <?php echo Lang::txt($label); ?>
                </div>
                <div class="col-sm-10 col-md-10 col-lg-10">
                    <?php echo $value?>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }

    function render($cls) {
        ob_start();
        ?>
            <div class="file <?php echo $cls ?>" id="<?php echo $this->id ?>">
                <div class="row">
                    <?php echo $this->renderMain($this->path, "col-sm-8 col-md-8 col-lg-8"); ?>
                    <?php echo $this->renderMain(formatBytes($this->filesize, 2), "col-sm-1 col-md-1 col-lg-1"); ?>
                    <?php echo $this->renderMain($this->locationName, "col-sm-2 col-md-2 col-lg-2"); ?>
                    <div class="show col-sm-1 col-md-1 col-lg-1">
                        <div class="btn btn-primary"><?php echo Lang::txt('COM_RADIAM_MORE'); ?></div>
                    </div>
                    <div class="hide col-sm-1 col-md-1 col-lg-1">
                        <div class="btn btn-secondary"><?php echo Lang::txt('COM_RADIAM_LESS'); ?></div>
                    </div>
                </div>
                <div class="extra-metadata-container">
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_ID', $this->id); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_NAME', $this->name); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_EXT', $this->extension); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_OWNER', $this->owner); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_GROUP', $this->group); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_PARENT', $this->pathParent); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_LAST_MODIFIED', $this->lastModified); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_LAST_CHANGE', $this->lastChange); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_LAST_ACCESS', $this->lastAccess); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_INDEXED_BY', $this->indexedBy); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_INDEXING_DATE', $this->indexingDate); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_TYPE', $this->type); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_AGENT', $this->agent); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_INDEXED_DATE', $this->indexedDate); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_PATH_PARENT_KEYWORD', $this->pathParentKeyword); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_PATH_KEYWORD', $this->pathKeyword); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_PATH_AGNOSTIC', $this->pathAgnostic); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_PATH_AGNOSTIC_KEYWORD', $this->pathAgnosticKeyword); ?>
                    <?php echo $this->renderExtra('COM_RADIAM_LABEL_ENTITY', $this->entity); ?>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }
}
