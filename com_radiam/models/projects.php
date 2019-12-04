<?php

namespace Components\Radiam\Models;

use Components\Radiam\Models\Project;
use Hubzero\Utility\String;
use Hubzero\Config\Registry;
use Hubzero\Form\Form;
use Filesystem;
use Component;
use Lang;
use User;
use Date;

class Projects
{
    public $count = 0;
    public $next = null;
    public $previous = null;
    public $projects = array();

    function __construct($projectsJson) {
        // var_dump($projectsJson);

        $this->count = $projectsJson->count;
        $this->next = $projectsJson->next;
        $this->previous = $projectsJson->previous;

        foreach ($projectsJson->results as &$projectJson) {
            $project = new Project($projectJson);
            array_push($this->projects, $project);
        }
        unset($projectJson);
    }

    function renderSelect($projectId) {
        ob_start();
        if ($this->count == 1) {
            echo "<p>" . $this->projects[0]->name . "</p>";
        } else if ($this->count > 1) {
            ?>
            <select id="projects">
            <?php
            foreach ($this->projects as &$project) {
                if (isset($projectId) && $project->id == $projectId) {
                    echo $project->renderSelect(true);
                } else {
                    echo $project->renderSelect(false);
                }
            }
            ?>
            </select>
            <?php
        }
        return ob_get_clean();
    }
}
