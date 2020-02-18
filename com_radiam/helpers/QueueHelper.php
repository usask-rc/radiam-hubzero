<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright 2005-2019 HUBzero Foundation, LLC.
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Radiam\Helpers;

use Components\Radiam\Models\Radtoken;
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radtoken.php';

use Components\Radiam\Helpers\RadiamAPI;
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'radiam_api.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Components\Projects\Models\Orm\Project;
require_once \Component::path('com_projects') . DS . 'models' . DS . 'orm' . DS . 'project.php';

// No direct access
defined('_HZEXEC_') or die('Restricted access');

/**
 * Raidam process radqueue table helper functions
 *
 */
class QueueHelper
{   
    /**
     * The logger
     *
     * @var logger
     */
    protected $logger = null;
    protected $config = array();
    protected $cSet = array();
    protected $dSet = array();
    protected $radiamAPI = null;
    protected $project_key = null;
    protected $project_config = null;

    public function __construct($config, $project_key, $logger)
    {   
        $this->project_key = $project_key;
        $this->logger = $logger;
        $this->config = $config;
        

        $this->logger->info("Construct the queue processor for project {$project_key}");
        
        $userId = $this->getProjectOwner($project_key);
        if ($userId == null) {
            $this->logger->error('Project does not exist.');
            throw new Exception();
        } 

        $tokens = getToken($userId);

        if ($tokens == null) {
            $this->logger->error('Please login first.');
            throw new Exception();
        } 

        $tokens_array = array (
            "access"  => $tokens->get('access_token'),
            "refresh" => $tokens->get('refresh_token')
        );
        $this->radiamAPI = new RadiamAPI($this->config["radiam_host_url"], $tokens_array, $this->logger, $userId);
    }

    function processQueue()
    {	
        $this->logger->info('Start processing file event queue');
        // $this->_loadConfig();

        list($this->config, $checkinStatus, $errMessage) = agentCheckin($this->radiamAPI, $this->config, $this->logger);
        $this->project_config = $this->config[$this->project_key];
        // file_put_contents("this_config", print_r($this->config, true));
        
        if (!$checkinStatus) {
            $this->logger->error($errMessage);
            exit();
        }

        $this->_db = App::get('db');
        $radiamQueue = $this->getRadiamQueue();
        if ($radiamQueue !== false)
        {
            foreach ($radiamQueue as $event)
            {	
                
                $result = $this->postToRadiamApi($event);
                $id = $event->id;
                // if ($result === 'success')
                // {	
                //     $this->deleteRadiamQueueRow($id);
                // }
                // elseif ($result === 'fail')
                // {
                //     $this->updateRadiamQueue($id);
                // }
            }
        }
    }
    protected function getRadiamQueue()
    {
        $this->_db = App::get('db');
        $sql = "SELECT `id`, `project_id`, `src_path`, `dest_path`, `action`
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
    protected function onCreated($event)
    {
        $this->onCreateModify($event, 'Created');
    }

    protected function onModified($event)
    {
        $this->onCreateModify($event, 'Modified');
    }

    protected function onMoved($event)
    {
        $srcPath = $event->src_path;
        // add a new field into the radiam queue table
        $destPath = $event->dest_path;
        $what = pathinfo($srcPath, PATHINFO_EXTENSION)? 'file': 'directory'; // determine whether the event is for a file or directory
        
        $this->logger->info("Moving {$what} from {$srcPath} to {$destPath}");
        array_push($this->dSet, $srcPath);
        array_push($this->cSet, $destPath);
        while (count($this->cSet) != 0) {
            $pathIn = array_pop($this->cSet);
            if ($what === 'directory')
            {
                $metadata = getDirMeta($pathIn, $this->config);
            }
            else 
            {
                $metadata = getFileMeta($pathIn, $this->config);
            }
            if ($metadata != null)
            {   
                // TODO: fix getToken bug
                // $token = $this->getToken();
                // $access_token = $token->get('access_token');
                tryConnectionInWorker($this->radiamAPI, $this->project_config, $pathIn, $this->logger, $metadata);
                // TODO: check if need set_last_crawl.add
            }
        }
        while (count($this->dSet) != 0) {
            $pathDe = array_pop($this->dSet);
            tryConnectionInWorker($this->radiamAPI, $this->project_config, $pathDe, $this->logger);
            // TODO: check if need set_last_crawl.add
            $this->logger->info("Moved {$what}: from {$srcPath} to {$destPath}");
        }
        list($metaStatusSrc, $parentPathSrc) = updatePath($srcPath, $this->config, $this->project_key, $this->radiamAPI, $this->project_config, $this->logger);
        list($metaStatusDest, $parentPathDest) = updatePath($destPath, $this->config, $this->project_key, $this->radiamAPI, $this->project_config, $this->logger);
        if ($metaStatusSrc === true) {
            // TODO: check if need set_last_crawl.add
            $this->logger->info("Update the information for directory {$parentPathSrc}");
        }
        if ($metaStatusDest === true) {
            // TODO: check if need set_last_crawl.add
            $this->logger->info("Update the information for directory {$parentPathDest}");
        }
    }

    protected function onDeleted($event) 
    {
        $this->logger->info("In onDeleted function");
        $res = $this->radiamAPI->searchEndpointByPath($this->project_config['endpoint'], $event->src_path);
        $what = 'unknown';
        if ($res != null) {
            foreach ($res->results as $doc) {
                if ($doc->type === 'directory') {
                    $what = 'directory';
                    array_push($this->dSet, $event->src_path);
                }
                elseif ($doc->type === 'file') {
                    $what = 'file';
                    array_push($this->dSet, $event->src_path);
                }
                else {
                    $what = 'unknown';
                    $this->logger->warning("The type is unknown.");
                }
            }
        }
        while (count($this->dSet) != 0) {
            $pathDe = array_pop($this->dSet);
            tryConnectionInWorker($this->radiamAPI, $this->project_config, $pathDe, $this->logger);
            // TODO: check if need set_last_crawl.add
            $this->logger->info("Deleted {$what}: {$event->src_path}");
            list($metaStatus, $parentPath) = updatePath($event->src_path, $this->config, $this->project_key, $this->radiamAPI, $this->project_config, $this->logger);
            if ($metaStatus === true) {
                // TODO: check if need set_last_crawl.add
                $this->logger->info("Update the information for directory {$parentPath}");
            }
        }
    }

    protected function onCreateModify($event, $action)
    {
        $path = $event->src_path;
        // $what = is_dir($path)? 'directory': 'file'; // determine whether the event is for a file or directory
        $what = pathinfo($path, PATHINFO_EXTENSION)? 'file': 'directory'; 
        $this->logger->info("create a {$what} with path {$path}");
        array_push($this->cSet, $path);
        while (count($this->cSet) != 0)
        {
            $pathIn = array_pop($this->cSet);
            if ($what === 'directory')
            {
                $metadata = getDirMeta($pathIn, $this->config);
            }
            else 
            {
                $metadata = getFileMeta($pathIn, $this->config);
            }
            // file_put_contents("metadata", print_r($metadata, true));
            
            if ($metadata != null)
            {   
                // TODO: fix getToken bug
                // $token = $this->getToken();
                // $access_token = $token->get('access_token');
                tryConnectionInWorker($this->radiamAPI, $this->project_config, $pathIn, $this->logger, $metadata);
                // TODO: check if need set_last_crawl.add
                $this->logger->info("{$action} {$what}: {$path}");
                // TODO: delete this, for debug purpose
                $metadataStr = json_encode($metadata);
                // $this->logger->info("{$metadataStr}");
            }

            list($metaStatus, $parentPath) = updatePath($path, $this->config, $this->project_key, $this->radiamAPI, $this->project_config, $this->logger);
            if ($metaStatus === true) 
            {
                // TODO: check if need set_last_crawl.add
                $this->logger->info("Update the information for directory {$parentPath}");
            }
        }    
    }

    protected function getProjectOwner($projectId) 
	{
        // TODO: decide whether should use oneOrFail or oneOrNew
        $project = Project::oneOrFail(intval($projectId));
        if ($project == null) {
            return null;
        }
		$ownerId = $project->get('owned_by_user');
		return $ownerId;
	}
    function postToRadiamApi($event)
    {   
        if ($event->action === 'create')
        {
            $this->onCreated($event);
        }
        elseif ($event->action === 'move')
        {
            $this->onMoved($event);
        }
        elseif ($event->action === 'delete')
        {
            $this->onDeleted($event);
        }
        return testResponse($event);
    }
}

function testResponse($event)
{
    $responses = array('fail', 'success');
    $resp = $responses[array_rand($responses, 1)];
    return $resp;
}

function getGroups($userId)
{   
    $groups = User::getAuthorisedGroups();
    if (empty($groups))
    {
        return null;
    }
    $db = App::get('db');

    $query = $db->getQuery()
        ->select('ug.title')
        ->from('#__usergroups', 'ug')
        ->join('#__user_usergroup_map AS m', 'm.group_id', 'ug.id', 'left')
        ->where('m.user_id', '=', $userId);

    $db->setQuery($query->toString());
    $result = $db->loadObject();

    return $result->title;
}

function getToken($userId)
{
    $token = false;
    // $user = User::getInstance($userId);
    if(User::isGuest())
    {
        return false;
    }
    $token = Radtoken::oneOrNew($userId);

    if ($token == null)
    {
        return false;
    }
    else
    {
        return $token;
    }
}

function tryConnectionInWorker($radiamAPI, $project_config, $path, $logger, $metadata=null)
{   
    $logger->info("In try connection in worker function");
    
    while (true) 
    {
        try {
            $logger->info("Before calling search endpoint by path");
            $res = $radiamAPI->searchEndpointByPath($project_config['endpoint'], $path);
            $logger->info("After calling search endpoint by path");
            // file_put_contents("endpoint_path_result", print_r($res, true));
            if ($res != null) 
            {
                if ($metadata != null)
                {
                    if ($res->count === 0)
                    {
                        $radiamAPI->createDocument($project_config['endpoint'], $metadata);
                        $metadataStr = json_encode($metadata);
                        $logger->debug("POSTing to API: {$metadataStr}");
                    }
                    else
                    {
                        $radiamAPI->createDocument($project_config['endpoint'], $metadata);
                        $metadataStr = json_encode($metadata);
                        $logger->debug("POSTing to API: {$metadataStr}");
                    }
                }
                else
                {
                    foreach ($res->results as $doc) {
                        $radiamAPI->deleteDocument($project_config['endpoint'], $doc->id);
                        $logger->info("DELETEing document {$doc->id} from API");
                    }
                }
            }     
            return;
        } catch (\Exception $e) {
            // file_put_contents("e", print_r($e, true));
            
            sleep(10);
        }
    }
}

function updatePath($path, $config, $project_key, $API, $project_config, $logger)
{   
    $parentPath = dirname($path);
    $metadata = getDirMeta($parentPath, $config, $project_key);
    if ($metadata != null) {
        tryConnectionInWorker($API, $project_config, $path, $logger, $metadata);
        return array(true, $parentPath);
    }
    else{
        return array(false, $parentPath);
    }
    return;
}
function getFileMeta($path, $config, $project_key=null)
{
    try{
        clearstatcache();
        $stat = stat($path);

        #TODO: Skip files smaller than minsize cli flag
        // if size < int(config['agent'].get('minsize',0)):
        //     return None

        $mtime = $stat['mtime']; // Time of last modification
        $atime = $stat['atime']; // Time of last access
        $ctime = $stat['ctime']; // TIme of last status change
        # Convert time in days (mtime cli arg) to seconds
        # TODO: check this
        // $time_sec = int($config['agent'].get('mtime',0)) * 86400;
        // $file_mtime_sec = time() - $mtime;
        // # Only process files modified at least x days ago
        // if ($file_mtime_sec < $time_sec)
        // {
        //     return null;        
        // }

        # convert times to utc for es, string in ios format
        $mtime_utc = gmdate('c', $mtime);
        $atime_utc = gmdate('c', $atime);
        $ctime_utc = gmdate('c', $ctime);

        # get user name
        $owner = User::get('name');

        # get group 
        $group = getGroups(User::get('id'));

        # get time
        $indextime_utc = gmdate('c', time());

        # get info from path
        $path_parts = pathinfo($path);
        # create file metadata array
        $filemeta = array(
            "name" =>$path_parts['basename'],
            "extension" => $path_parts['extension'],
            "path_parent" => $path_parts['dirname'],
            "path" => $path,
            "filesize" => $stat['size'],
            "owner" => $owner,
            "group" => $group,
            "last_modified" => $mtime_utc,
            "last_access" => $atime_utc,
            "last_change" => $ctime_utc,
            "indexed_by" => $owner,
            "indexing_date" => $indextime_utc,
            "type" => "file",
            "location" => $config['location_id'],
            "agent" => $config['agent_id']
        );
    } catch (\Exception $e) {
        // file_put_contents("e", print_r($e, true));
        return false;
    }
    return $filemeta; 
}
function getDirMeta($path, $config)
{
    try{
        clearstatcache();
        $stat = stat($path);

        $mtime = $stat['mtime']; // Time of last modification
        $atime = $stat['atime']; // Time of last access
        $ctime = $stat['ctime']; // TIme of last status change
        
        # convert times to utc for es, string in ios format
        $mtime_utc = gmdate('c', $mtime);
        $atime_utc = gmdate('c', $atime);
        $ctime_utc = gmdate('c', $ctime);

        # get user name
        $owner = User::get('name');

        # get group 
        $group = getGroups(User::get('id'));

        # get time
        $indextime_utc = gmdate('c', time());

        # get info from path
        $path_parts = pathinfo($path);

        # get number of items and files in the directory
        list($itemcount, $filecount) = getDirItemsCount($path);
        # create file metadata array
        $dirmeta = array(
            "name" =>$path_parts['basename'],
            "path" => $path,
            "path_parent" => $path_parts['dirname'],
            "items" => $itemcount,
            "file_num_in_dir" => $filecount,
            "owner" => $owner,
            "group" => $group,
            "last_modified" => $mtime_utc,
            "last_access" => $atime_utc,
            "last_change" => $ctime_utc,
            "indexed_by" => $owner,
            "indexing_date" => $indextime_utc,
            "type" => "directory",
            "location" => $config['location']['id'],
            "agent" => $config['agent_id']
        );
    } catch (\Exception $e) {
        return false;
    }
    return $dirmeta;
}
function getDirItemsCount($directory)
{   
    if (substr($directory, -1) != DS) {
        $directory = $directory . DS;      
    }

    $filecount = 0;
    $itemcount = 0;
    $files = glob($directory . "*.*");
    if ($files){
        $filecount = count($files);
    }
    $items = glob($directory . "*");
    if ($items){
        $itemcount = count($items);
    }
    return array($filecount, $itemcount);
}

// TODO: move this outside queuehelper
function agentCheckin($API, $config, $logger) 
{   
    $defaultLocationType = "location.type.server";
    $version = '0.1';
    $host = $config['radiam_host_url'];
    foreach ($config['projects'] as $project_key) {
        if (array_key_exists('radiam_project_uuid', $config[$project_key]) and $config[$project_key]['radiam_project_uuid']) {
            $config[$project_key]['endpoint'] = $host . "api/projects/" . $config[$project_key]['radiam_project_uuid'] . "/";
            $res = $API->searchEndpointByName('projects', $config[$project_key]['radiam_project_uuid'], "id");
            if (!$res or !isset($res->results) or count($res->results) == 0) {
                return array($config, false, "Radiam project id {$config[$project_key]['radiam_project_uuid']} does not appear to exist - was it deleted?");
            }
        }
        else {
            return array($config, false, "No radiam project id provided. Please set it up in the Radiam Component page.");
        }

        // TODO: add assert function for agent_id and location_name

        // Create the location if needed
        if (!array_key_exists('location_id', $config)) {
            $res = $API->searchEndpointByName('locations', $config['location_name'], "display_name");
            // file_put_contents("locations_result", print_r($res, true));
            if ($res and isset($res->results) and count($res->results) > 0) {
                $config['location_id'] = $res->results[0]->id;
            }
            else {
                $res = $API->searchEndpointByName('locationtypes', $defaultLocationType, "label");
                // file_put_contents("locationtypes_result", print_r($res, true));
                if ($res and isset($res->results) and count($res->results) > 0) {
                    // file_put_contents("locationtypes_result_results", print_r($res->results, true));
                    $locationId = $res->results[0]->id;
                }
                else {
                    return array($config, false, "Could not look up location type ID for {$defaultLocationType}");
                }
                $hostname = gethostname();
                $newLocation = array(
                    "display_name" => $config['location_name'],
                    "host_name"    => $hostname,
                    "location_type"=> $locationId
                );
                $res = $API->createLocation($newLocation);
                // file_put_contents("create_location_result",print_r($res, true));
                if ($res and isset($res->id)) {
                    $config['location_id'] = $res->id;
                }
                else {
                    return array($config, false, "Tried to create a new location, but the API call failed.");
                }
            }
            // Write the location id to the radconfig table
            $db = App::get('db');
            $sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`, `created`) 
                    VALUES ('location_id', '{$config['location_id']}', now());";
            $db->setQuery($sql);
            $db->query();
        }
        // Create the user agent if needed
        $res = $API->searchEndpointByName('useragents', $config['agent_id'], "id");
        if (!$res or !isset($res->results) or count($res->results) == 0) {
            $logger->info("Useragent {$config['agent_id']} was not found in the remote system; creating it now.");
            $res = $API->getLoggedInUser();

            if ($res and isset($res->id)) {
                $currentUserId = $res->id;
                $projectConfigList = array();
                //TODO: add info into project config list
                // for p in config['projects']['project_list']:
                //     project_config_list.append({
                //         "project": config[p]['name'],
                //         "config": {"rootdir": config[p]['rootdir']}
                //     })
                $newAgent = array(
                    "id" => $config['agent_id'],
                    "user" => $currentUserId,
                    "version" => $version,
                    "location" => $config['location_id'],
                    "project_config_list" => $projectConfigList
                );
                $logger->debug(sprintf("JSON: %s", json_encode($newAgent)));
                $res = $API->createUserAgent($newAgent);
                // file_put_contents("create_user_agent_result",print_r($res, true));
                if (!$res or !isset($res->id)) {
                    return array($config, false, "Tried to create a new user agent, but the API call failed.");
                }
                else {
                    $logger->info("User agent {$config['agent_id']} created.");
                }
            }
            else {
                $logger->error("Could not determine current logged in user to create user agent.");
                return array($config, false, "Could not determine current logged in user to create user agent.");
            }
        }
        else {
            $logger->debug("User agent {$config['agent_id']} appears to exist.");
        }
    }
    return array($config, true, null);
}

