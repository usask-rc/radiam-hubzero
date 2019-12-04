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

class Project
{

    public $url = "";
    public $id = "";
    public $name = "";
    public $group = "";
    public $projectHost = "";
    public $projectIndex = "";
    public $created = "";
    public $updated = "";

    function __construct($projectJson) {
        $this->url = $projectJson->url;
        $pieces = explode("/", substr($projectJson->url, 0, -1));
        $this->id = end($pieces);
        $this->name = $projectJson->name;
        $this->group = $projectJson->group;
        $this->projectHost = $projectJson->es_host;
        $this->projectIndex = $projectJson->es_index;
        $this->created = $projectJson->date_created;
        $this->updated = $projectJson->date_updated;
    }

    function renderSelect($selected) {
        ob_start();
        ?>
            <option value="<?php echo $this->id ?>" <?php if ($selected) { ?> selected <?php } ?>><?php echo $this->name ?></option>
        <?php
        return ob_get_clean();
    }
}
