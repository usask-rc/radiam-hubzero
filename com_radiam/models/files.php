<?php

namespace Components\Radiam\Models;

use Components\Radiam\Models\File;
use Hubzero\Utility\String;
use Hubzero\Config\Registry;
use Hubzero\Form\Form;
use Filesystem;
use Component;
use Lang;
use User;
use Date;

class Files
{
    public $count = 0;
    public $next = null;
    public $previous = null;
    public $files = array();

    function __construct($json=null) {
        if (isset($json) && isset($json->results)) {
            $hasCount = false;
            if (isset($json->count)) {
                $this->count = $json->count;
            }
            foreach ($json->results as &$fileJson) {
                $file = new File($fileJson);
                array_push($this->files, $file);
            }
            unset($fileJson);
        } else {
            $this->count = 0;
        }
   }
}
