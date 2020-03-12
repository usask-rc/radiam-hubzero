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

    function __construct($filesJson=null, $locationsArray=null) {
        if (isset($filesJson) && isset($filesJson->results)) {
            $hasCount = false;
            if (isset($filesJson->count)) {
                $this->count = $filesJson->count;
            }
            foreach ($filesJson->results as &$fileJson) {
                $fileId = $fileJson->id;
                $locationName = $locationsArray[$fileId];
                $file = new File($fileJson, $locationName);
                array_push($this->files, $file);
            }
            unset($fileJson);
        } else {
            $this->count = 0;
        }
   }
}
