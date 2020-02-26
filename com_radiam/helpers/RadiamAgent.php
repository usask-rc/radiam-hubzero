<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

namespace Components\Radiam\Helpers;

use Components\Radiam\Models\Radtoken;
use Components\Radiam\Helpers\RadiamAPI;
use Components\Projects\Models\Orm\Project;
use Exception;
use FilesystemIterator;
use SplQueue;

require_once \Component::path('com_projects') . DS . 'models' . DS . 'orm' . DS . 'project.php';
require_once \Component::path('com_radiam') . DS . 'models' . DS . 'radtoken.php';
require_once \Component::path('com_radiam') . DS . 'helpers' . DS . 'radiam_api.php';

// No direct access
defined('_HZEXEC_') or die('Restricted access');

/**
 * Raidam Agent Class
 *
 */
class RadiamAgent
{   
    /**
     * The logger
     *
     * @var null
     */
    private $logger = null;

    /**
     * The radiam hubzero agent config 
     * 
     * @var array
     */
    private $config = array();

    /**
     * The set containing files to create
     *
     * @var array
     */
    private $cSet = array();

    /**
     * The set containing files to delete
     *
     * @var array
     */
    private $dSet = array();

    /**
     * Radiam API Object
     *
     * @var object
     */
    private $radiamAPI = null;

    /**
     * The project id that is being monitored
     *
     * @var int
     */
    private $project_key = null;

    /**
     * The monitored project config
     *
     * @var array
     */
    private $project_config = array();

    /**
	 * Database instance
	 *
	 * @var  object
	 */
	private $_db = null;

    /**
     * @const int The maximum data size allowed to POST
     */
    const POST_DATA_LIMIT = 1000000;

    /**
     * Constructor
     *
     * @param array $config The radiam hubzero agent config 
     * @param int $project_key The project id that is being monitored
     * @param object $logger The logger
     */
    public function __construct($config, $project_key, $logger)
    {   
        $this->project_key = $project_key;
        $this->logger = $logger;
        $this->config = $config;

        // Initialize DB
		$this->_db = App::get('db');
        
        $userId = $this->getProjectOwner($project_key);
        if ($userId == null) {
            $this->logger->error('Project does not exist.');
            throw new Exception('Project does not exist.');
        } 
        $this->userId = $userId;

        $this->logger->info("Inside constructor, user id is {$userId}");
        $tokens = $this->getToken($userId);

        if ($tokens == null) {
            $this->logger->error('Please login first.');
            throw new Exception('Please login first.');
        } 
        $tokens_array = array (
            "access"  => $tokens->get('access_token'),
            "refresh" => $tokens->get('refresh_token')
        );

        $this->radiamAPI = new RadiamAPI($this->config["radiam_host_url"], $tokens_array, $this->logger, $userId);

        list($checkinStatus, $errMessage) = $this->agentCheckin();
        $this->project_config = $this->config[$this->project_key];
        // file_put_contents("this_config", print_r($this->config, true));
        
        if (!$checkinStatus) {
            $this->logger->error($errMessage);
            throw new Exception("Agent failed to checkin with error message {$errMessage}.");
        }
    }

    /**
     * Check in to API as agent to set up IDs
     *
     * @param object $API The radiam API object
     * @param  array  $config  The radiam hubzero agent config 
     * @param  object  $logger  The logger
     * @return array The checkin status and error message
     */
    private function agentCheckin() 
    {   
        $defaultLocationType = "location.type.hubzero";
        $version = "1.2.0";
        $host = $this->config['radiam_host_url'];
        if (array_key_exists('radiam_project_uuid', $this->config[$this->project_key]) and $this->config[$this->project_key]['radiam_project_uuid']) {
            $this->config[$this->project_key]['endpoint'] = $host . "api/projects/" . $this->config[$this->project_key]['radiam_project_uuid'] . "/";
            $res = $this->radiamAPI->searchEndpointByName('projects', $this->config[$this->project_key]['radiam_project_uuid'], "id");
            if (!$res or !isset($res->results) or count($res->results) == 0) {
                return array(false, "Radiam project id {$this->config[$this->project_key]['radiam_project_uuid']} does not appear to exist - was it deleted?");
            }
        }
        else {
            return array(false, "No radiam project id provided. Please set it up in the Radiam Component page.");
        }

        // Create the location if needed
        if (!array_key_exists('location_id', $this->config)) {
            $res = $this->radiamAPI->searchEndpointByName('locations', $this->config['location_name'], "display_name");
            // file_put_contents("locations_result", print_r($res, true));
            if ($res and isset($res->results) and count($res->results) > 0) {
                $this->config['location_id'] = $res->results[0]->id;
            }
            else {
                $res = $this->radiamAPI->searchEndpointByName('locationtypes', $defaultLocationType, "label");
                // file_put_contents("locationtypes_result", print_r($res, true));
                if ($res and isset($res->results) and count($res->results) > 0) {
                    // file_put_contents("locationtypes_result_results", print_r($res->results, true));
                    $locationId = $res->results[0]->id;
                }
                else {
                    return array(false, "Could not look up location type ID for {$defaultLocationType}");
                }
                $hostname = gethostname();
                $newLocation = array(
                    "display_name" => $this->config['location_name'],
                    "host_name"    => $hostname,
                    "location_type"=> $locationId
                );
                $res = $this->radiamAPI->createLocation($newLocation);
                // file_put_contents("create_location_result",print_r($res, true));
                if ($res and isset($res->id)) {
                    $this->config['location_id'] = $res->id;
                }
                else {
                    return array(false, "Tried to create a new location, but the API call failed.");
                }
            }
            // Write the location id to the radconfig table
            $db = App::get('db');
            $currentUserId = User::get('id');
            $sql = "INSERT INTO `#__radiam_radconfigs` (`configname`, `configvalue`, `created`, `created_by`) 
                    VALUES ('location_id', '{$this->config['location_id']}', now(), $currentUserId);";
            $db->setQuery($sql);
            $db->query();
        }
        // Create the user agent if needed
        $res = $this->radiamAPI->searchEndpointByName('useragents', $this->config['agent_id'], "id");
        if (!$res or !isset($res->results) or count($res->results) == 0) {
            $this->logger->info("Useragent {$this->config['agent_id']} was not found in the remote system; creating it now.");
            $res = $this->radiamAPI->getLoggedInUser();

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
                    "id" => $this->config['agent_id'],
                    "user" => $currentUserId,
                    "version" => $version,
                    "location" => $this->config['location_id'],
                    "project_config_list" => $projectConfigList
                );
                $this->logger->debug(sprintf("JSON: %s", json_encode($newAgent)));
                $res = $this->radiamAPI->createUserAgent($newAgent);
                // file_put_contents("create_user_agent_result",print_r($res, true));
                if (!$res or !isset($res->id)) {
                    return array(false, "Tried to create a new user agent, but the API call failed.");
                }
                else {
                    $this->logger->info("User agent {$this->config['agent_id']} created.");
                }
            }
            else {
                $this->logger->error("Could not determine current logged in user to create user agent.");
                return array(false, "Could not determine current logged in user to create user agent.");
            }
        }
        else {
            $this->logger->debug("User agent {$this->config['agent_id']} appears to exist.");
        }
        return array(true, null);
    }


    /**
     * Crawl the entire directory for all projects that with no files indexed
     * 
     * @return void
     */
    public function fullRun() 
    {  
        $queue = new SplQueue();
        while (true) {
            try {
                $this->logger->info("Checking if files indexed for Project {$this->project_key}.");
                // if there is no files in a project, then run the initial crawl
                $res = $this->radiamAPI->searchEndpoint($this->config[$this->project_key]["endpoint"]);      
                if ($res->count > 0) {
                    $this->logger->info("There are files indexed for Project {$this->project_key}.");
                    return;
                }
                $this->logger->info("Start to full run the Project {$this->project_key}.");
                $queue->push($this->config[$this->project_key]['rootdir']);
                $files = array();
                $bulkdata = array();
                $bulksize = 0;

                // nested function 
                $postData = function($metadata, $entry, $bulksize, $bulkdata) use (&$files)
                {
                    $repsText = null;
                    $status = false;
                    if ($metadata == null or gettype($metadata) == "array" and count($metadata) == 0) {       
                    }
                    else {
                        array_push($files, $entry);
                        $metasize = mb_strlen(json_encode($metadata), "8bit");
                        if (($metasize + $bulksize) > self::POST_DATA_LIMIT) {
                            list($repsText, $status) = $this->tryConnectionInWorkerBulk($bulkdata);
                            $bulkdata = array();
                            array_push($bulkdata, $metadata);
                            $bulksize = mb_strlen(json_encode($bulkdata), "8bit");
                        }
                        else {
                            array_push($bulkdata, $metadata);
                            $bulksize = mb_strlen(json_encode($bulkdata), "8bit");
                        }
                    }
                    return array($bulkdata, $bulksize, $repsText, $status);
                };
                while (!$queue->isEmpty()) {
                    try {
                        $path = $queue->pop();
                        try {
                            $fs = new FilesystemIterator($path);
                            // entry includes the filename
                            foreach ($fs as $entry) {
                                if ($entry->isDir()) {
                                    $queue->push($entry->getPathname());
                                    $metadata = $this->getDirMeta($entry->getPathname());
                                    list($bulkdata, $bulksize, $respText, $status) = $postData($metadata, $entry, $bulksize, $bulkdata);
                                }
                                else if ($entry->isFile()) {
                                    $metadata = $this->getFileMeta($entry->getPathname(), $this->project_key);
                                    list($bulkdata, $bulksize, $respText, $status) = $postData($metadata, $entry, $bulksize, $bulkdata);
                                }
                            }
                        } catch(Exception $e) {
                            $this->logger->warning($e);
                        }
                    } catch(Exception $e) {
                        $this->logger->warning($e);
                        break;
                    }
                }
                file_put_contents("files.txt", print_r($files, true));
                if ($bulkdata == null or gettype($bulkdata) == "array" and count($bulkdata) == 0) {
                    $this->logger->info("No files to index on Project {$this->project_key}");
                    $this->logger->info(sprintf("Agent has added %s items to Project %s", count($files), $this->project_key));
                    return array(null, 200);
                }
                else {
                    list($repsText, $status) = $this->tryConnectionInWorkerBulk($bulkdata);
                }
                if ($status) {
                    $this->logger->info("Finished indexing files to Project {$this->project_key}");
                    $this->logger->info(sprintf("Agent has added %s items to Project %s", count($files), $this->project_key));
                    return array($repsText, $status);
                }
                else {
                    return array($respText, $status);
                }
            } catch (Exception $e) {
                // file_put_contents("e", print_r($e, true));
                throw new Exception($e);
                sleep(10);
            }
        }
    }

    /**
     * Process the radqueue table, remove the row if sent to radiam API successfully
     *
     * @return void
     */
    public function processQueue()
    {	
        $this->logger->info('Start to process file event queue.');

        $radiamQueue = $this->getRadiamQueue();
        if ($radiamQueue)
        {
            foreach ($radiamQueue as $event)
            {	
                
                $result = $this->postToRadiamApi($event);
                $id = $event->id;
                if ($result)
                {	
                    $this->deleteRadiamQueueRow($id);
                }
                else
                {
                    $this->updateRadiamQueue($id);
                }
            }
        }
    }


    /**
     * Get all the records in radqueue table
     *
     * @return array The array of SQL query results
     */
    private function getRadiamQueue()
    {
        $sql = "SELECT `id`, `project_id`, `src_path`, `dest_path`, `action`
                FROM `#__radiam_radqueue`
                WHERE `project_id` = '{$this->project_key}'
                ORDER BY `created` ASC";
        $this->_db->setQuery($sql);
        $this->_db->query();
    
        if (!$this->_db->getNumRows())
        {
            return null;
        }
        $radiamQueue = $this->_db->loadObjectList();
        return $radiamQueue;
    }


    /**
     * Delete a row by id in radqueue table
     *
     * @param int $id The primary key of radqueue table
     * @return void
     */
    private function deleteRadiamQueueRow($id)
    {
        $sql = "DELETE FROM `#__radiam_radqueue`
                WHERE `id` = '{$id}'";
        $this->_db->setQuery($sql);
        $this->_db->query();
    }


    /**
     * Update the last_modified field of a row by id in radqueue table
     *
     * @param int $id The primary key of radqueue table
     * @return void
     */
    private function updateRadiamQueue($id)
    {
        $sql = "UPDATE `#__radiam_radqueue`
                SET `last_modified` = now()
                WHERE `id` = '{$id}'";
        $this->_db->setQuery($sql);
        $this->_db->query();
    }


    /**
     * Actions to take on a create file system event
     *
     * @param object $event A row in the radqueue table, representing a file system event
     * @return boolean Whether the data is sent to the server successfully or not
     */
    private function onCreated($event)
    {   
        return $this->onCreateModify($event, 'Created');
    }

    /**
     * Actions to take on a modify file system event
     *
     * @param object $event A row in the radqueue table, representing a file system event
     * @return void
     */
    private function onModified($event)
    {
        return $this->onCreateModify($event, 'Modified');
    }

    /**
     * Actions to take on a move file system event
     *
     * @param object $event A row in the radqueue table, representing a file system event
     * @return boolean Whether the data is sent to the server successfully or not
     */
    private function onMoved($event)
    {   
        $this->logger->info("Moved event has been captured. Handling it...");
        try {
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
                    $metadata = $this->getDirMeta($pathIn);
                }
                else 
                {
                    $metadata = $this->getFileMeta($pathIn);
                }
                if ($metadata != null)
                {   
                    $this->tryConnectionInWorker($pathIn, $metadata);
                }
            }
            while (count($this->dSet) != 0) {
                $pathDe = array_pop($this->dSet);
                $this->tryConnectionInWorker($pathDe);
                $this->logger->info("Moved {$what}: from {$srcPath} to {$destPath}");
            }
            list($metaStatusSrc, $parentPathSrc) = $this->updatePath($srcPath);
            list($metaStatusDest, $parentPathDest) = $this->updatePath($destPath);
            if ($metaStatusSrc === true) {
                $this->logger->info("Update the information for directory {$parentPathSrc}");
            }
            if ($metaStatusDest === true) {
                $this->logger->info("Update the information for directory {$parentPathDest}");
            }
        } catch(Exception $e) {
            $this->logger->error($e);
            return false;
        }  
        $this->logger->info("Finish handling a move event.");   
        return true;
    }


    /**
     * Actions to take on a delete file system event
     *
     * @param object $event A row in the radqueue table, representing a file system event
     * @return boolean Whether the data is sent to the server successfully or not
     */
    private function onDeleted($event) 
    {
        $this->logger->info("Deleted event has been captured. Handling it...");
        try {
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
                $this->tryConnectionInWorker($pathDe);
                $this->logger->info("Deleted {$what}: {$event->src_path}");
                list($metaStatus, $parentPath) = $this->updatePath($event->src_path);
                if ($metaStatus === true) {
                    $this->logger->info("Update the information for directory {$parentPath}");
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e);
            return false;
        }
        $this->logger->info("Finish handling a delete event.");
        return true;
    }

    /**
     * Actions to take on a create or modify file system event
     *
     * @param object $event A row in the radqueue table, representing a file system event
     * @param string $action
     * @return boolean Whether the data is sent to the server successfully or not
     */
    private function onCreateModify($event, $action)
    {   
        $this->logger->info("{$action} event has been captured. Handling it...");
        try {
            $path = $event->src_path;
            // determine whether the event is for a file or directory
            $what = pathinfo($path, PATHINFO_EXTENSION)? 'file': 'directory'; 
            $this->logger->info("create a {$what} with path {$path}");
            array_push($this->cSet, $path);
            while (count($this->cSet) != 0)
            {
                $pathIn = array_pop($this->cSet);
                if ($what === 'directory')
                {
                    $metadata = $this->getDirMeta($pathIn);
                }
                else 
                {
                    $metadata = $this->getFileMeta($pathIn);
                }
                if ($metadata != null)
                {   
                    $this->tryConnectionInWorker($pathIn, $metadata);
                    $this->logger->info("{$action} {$what}: {$path}");
                }
    
                list($metaStatus, $parentPath) = $this->updatePath($path);
                if ($metaStatus === true) 
                {
                    $this->logger->info("Update the information for directory {$parentPath}");
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e);
            return false;
        }
        $this->logger->info("Finish handling a {$action} event.");
        return true;
    }

    
    /**
     * Post to Radiam API on different file system events
     *
     * @param  object $event
     * @return boolean $result Whether send data to Radiam API successfully or not
     */
    private function postToRadiamApi($event)
    {   
        if ($event->action === 'create')
        {
            $result = $this->onCreated($event);
        }
        elseif ($event->action === 'move')
        {
            $result = $this->onMoved($event);
        }
        elseif ($event->action === 'delete')
        {
            $result = $this->onDeleted($event);
        }
        return $result;
    }


    /**
     * Helper function: get the project owner id by project id
     *
     * @param int $projectId
     * @return int
     */
    private function getProjectOwner($projectId) 
	{
        $project = Project::one(intval($projectId));
        if ($project === false) {
            return null;
        }
		$ownerId = $project->get('owned_by_user');
		return $ownerId;
    }


    /**
     * Helper function: get the user group by user id
     *
     * @param int $userId
     * @return void
     */
    private function getGroups($userId)
    {   
        $groups = User::getAuthorisedGroups();
        if (empty($groups))
        {
            return null;
        };

        $query = $this->_db->getQuery()
            ->select('ug.title')
            ->from('#__usergroups', 'ug')
            ->join('#__user_usergroup_map AS m', 'm.group_id', 'ug.id', 'left')
            ->where('m.user_id', '=', $userId);

        $this->_db->setQuery($query->toString());
        $result = $this->_db->loadObject();

        return $result->title;
    }

    /**
     * Get the radiam token by user id
     *
     * @param int $userId
     * @return object $token
     */
    private function getToken($userId)
    {
        $token = null;

        // Cron job will return here if checking whether the user is a guest
        // if(User::isGuest())
        // {   
        //     $this->logger->info("User is guest.");
        //     return null;
        // }
        $token = Radtoken::one($userId);
        if ($token === false)
        {
            return null;
        }
        else
        {
            return $token;
        }
    }

    /**
     * Connect to the Radiam Server to create or delete a document
     * 
     * @param string $path The path of the document
     * @param array $metadata The metadata of the document
     * @return void
     */
    private function tryConnectionInWorker($path, $metadata=null)
    {   
        while (true) 
        {
            try {
                $res = $this->radiamAPI->searchEndpointByPath($this->project_config['endpoint'], $path);
                if ($res != null) 
                {
                    if ($metadata != null)
                    {
                        $this->radiamAPI->createDocument($this->project_config['endpoint'], $metadata);
                        $metadataStr = json_encode($metadata);
                        $this->logger->debug("POSTing to API: {$metadataStr}");
                    }
                    else
                    {
                        foreach ($res->results as $doc) {
                            $this->radiamAPI->deleteDocument($this->project_config['endpoint'], $doc->id);
                            $this->logger->info("DELETEing document {$doc->id} from API");
                        }
                    }
                }     
                return;
            } catch (Exception $e) {
                // file_put_contents("e", print_r($e, true));              
                sleep(10);
            }
        }
    }

    /**
     * Connect to the Radiam Server to create multiple documents
     * 
     * @param array $metadata The metadata of the file or folder
     * @return void
     */
    private function tryConnectionInWorkerBulk($metadata)
    {   
        $this->logger->info("In try connection in worker bulk function");
        
        while (true) 
        {
            try {
                list($respText, $status) = $this->radiamAPI->createDocumentBulk($this->project_config['endpoint'], $metadata);
                if ($respText) 
                {
                    if (gettype($respText) == "array")
                    {
                        foreach($respText as $s) {
                            // check if s is a associative array
                            if (gettype($s) == "array" and array_keys($s) !== range(0, count($s) - 1)) {
                                if (!$s['result']) {
                                    $this->logger->error("Error sending file to API: {$s['docname'] {$s['result']}}");
                                }
                            }
                        }
                    }
                    else {
                        $this->logger->error("Radiam API error with index {$this->project_config['endpoint']}: {$respText}");
                    }
                }
                return array($respText, $status);
            } catch (\Exception $e) {
                // file_put_contents("e", print_r($e, true));      
                sleep(10);
            }
        }
    }

    
    /**
     * Update the parent path metadata
     *
     * @param string $path The path of the document
     * @return array $status, $parentPath
     */
    private function updatePath($path)
    {   
        $parentPath = dirname($path);
        $metadata = $this->getDirMeta($parentPath);
        if ($metadata != null) {
            $this->tryConnectionInWorker($path, $metadata);
            return array(true, $parentPath);
        }
        else{
            return array(false, $parentPath);
        }
        return;
    }


    /**
     * Generate the metadata of a file
     *
     * @param string $path The path of the file
     * @param int $project_key
     * @return array $filemeta The metadata of the file 
     */
    private function getFileMeta($path, $project_key=null)
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
            // $owner = User::get('name');
            $ownerObject = User::oneOrFail($this->userId);
            $owner = $ownerObject->name;

            # get group 
            $group = $this->getGroups($this->userId);

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
                "location" => $this->config['location_id'],
                "agent" => $this->config['agent_id']
            );
        } catch (Exception $e) {
            // file_put_contents("e", print_r($e, true));
            return null;
        }
        return $filemeta; 
    }


    /**
     * Generate the metadata of a directory
     *
     * @param string $path The path of the directory
     * @return array $dirmeta The metadata of the directory 
     */
    private function getDirMeta($path)
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
            $ownerObject = User::oneOrFail($this->userId);
            $owner = $ownerObject->name;

            # get group 
            $group = $this->getGroups($this->userId);

            # get time
            $indextime_utc = gmdate('c', time());

            # get info from path
            $path_parts = pathinfo($path);

            # get number of items and files in the directory
            list($filecount, $itemcount) = $this->getDirItemsCount($path);
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
                "location" => $this->config['location_id'],
                "agent" => $this->config['agent_id']
            );
        } catch (Exception $e) {
            return null;
        }
        return $dirmeta;
    }


    /**
     * Helper function: Get the number of files and items in a directory
     *
     * @param string $directory
     * @return array $filecount, $itemcount
     */
    private function getDirItemsCount($directory)
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
}