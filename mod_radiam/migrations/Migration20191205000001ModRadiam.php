<?php  
  
use Hubzero\Content\Migration\Base;  
  
// No direct access  
defined('_HZEXEC_') or die();  
  
/** 
 * Migration script for registering the example plugin
 **/  
class Migration20191205000001ModRadiam extends Base
{  
    /** 
     * Up 
     **/  
    public function up()
    {  
        // Register the module
        //  
        // @param   string  $element  (required) Module element
        // @param   int     $enabled  (optional, default: 1) Whether or not the module should be enabled
        // @param   string  $params   (optional) Plugin params (if already known)
        // @param   int     $client   (optional, default: 0) Client [site=0, admin=1]
        $this->addModuleEntry('mod_radiam');  
    }  
  
    /** 
     * Down 
     **/  
    public function down()
    {  
        $this->deleteModuleEntry('mod_radiam');
    }  
} 