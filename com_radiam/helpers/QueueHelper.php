<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Radiam\Helpers;

// No direct access
defined('_HZEXEC_') or die('Restricted access');

/**
 * Raidam process radqueue table helper functions
 *
 */
class QueueHelper
{   
    function processQueue()
    {	
        $this->_db = App::get('db');
        $radiamQueue = $this->getRadiamQueue();
        if ($radiamQueue !== false)
        {
            foreach ($radiamQueue as $event)
            {	
                
                $result = postToRadiamApi($event);
                $id = $event->id;
                if ($result === 'success')
                {	
                    $this->deleteRadiamQueueRow($id);
                }
                elseif ($result === 'fail')
                {
                    $this->updateRadiamQueue($id);
                }
            }
        }
    }
    protected function getRadiamQueue()
    {
        $this->_db = App::get('db');
        $sql = "SELECT `id`, `project_id`, `path`, `action`
                FROM `#__radiam_radqueue`
                ORDER BY `created` ASC";
        $this->_db->setQuery($sql);
        $this->_db->query();
    
        if (!$this->_db->getNumRows())
        {
            return false;
        }
    
        $radiamQueue = $this->_db->loadObjectList();
        return $radiamQueue;
    }
    protected function deleteRadiamQueueRow($id)
    {
        $this->_db = App::get('db');
        $sql = "DELETE FROM `#__radiam_radqueue`
                WHERE `id` = '{$id}'";
        $this->_db->setQuery($sql);
        $this->_db->query();
    }
    protected function updateRadiamQueue($id)
    {
        $this->_db = App::get('db');
        $sql = "UPDATE `#__radiam_radqueue`
                SET `last_modified` = now()
                WHERE `id` = '{$id}'";
        $this->_db->setQuery($sql);
        $this->_db->query();
    }
}
    
    function postToRadiamApi($event)
    {
        return testResponse($event);
    }
    
    function testResponse($event)
    {
        $responses = array('fail', 'success');
        $resp = $responses[array_rand($responses, 1)];
        return $resp;
    }