<?php
/**
 * @copyright  Copyright 2019 University of Saskatchewan and Simon Fraser University
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

// Get repo model
require_once Component::path('com_projects') . DS . 'models' . DS . 'repo.php';

/**
 * projects radiam plugin
 */
class plgProjectsRadiam extends \Hubzero\Plugin\Plugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 */
	protected $_autoloadLanguage = false;

	/**
	 * Mapping between file actions and radiamqueue table actions
	 *
	 * @var  array
	 */
	protected $_actionMapping = array(
		'removeit' => 'delete',
		'moveit'   => 'move',
		'renameit' => 'move',
		'savedir'  => 'create',
		'save'     => 'create',
		'update'   => 'update'
	);

	/**
	 * Event call for a specific project
	 *
	 * @param   object  $model   Project model
	 * @param   string  $action  Plugin task
	 * @param   string  $areas   Plugins to return data
	 * @param	array 	$params	 Plugins parameters
	 * @return  void
	 */
	public function onProject($model, $action = '', $areas = null, $params = array())
	{
		$this->db = App::get('db');
		$this->model = $model;
		$this->action = $action;

		// Load repo model
		$repoName   = !empty($params['repo']) ? $params['repo'] : Request::getString('repo', 'local');
		$this->repo = new \Components\Projects\Models\Repo($model, $repoName);
		
		$this->projectId = $this->repo->get('project')->get('id');
		$this->subdir = trim(urldecode(Request::getString('subdir', '')), DS);
		
		// $path = \Components\Projects\Helpers\Html::getProjectRepoPath(strtolower($project), 'files');
		$this->path = $this->repo->get('path'); 
		
		// Query the ordering of radiam plugin and files plugin
		$queryOrderingFilesPlg = "SELECT `ordering` FROM `#__extensions` WHERE `folder`='projects' AND `element`='files'";
		$this->db->setQuery($queryOrderingFilesPlg);
		$orderingFilesPlg = $this->db->loadObject()->ordering;

		$queryOrderingRadiamPlg = "SELECT `ordering` FROM `#__extensions` WHERE `folder`='projects' AND `element`='radiam'";
		$this->db->setQuery($queryOrderingRadiamPlg);
		$orderingRadiamPlg = $this->db->loadObject()->ordering;

		// The order of executing the radiam plugin has to be smaller than that of the files plugin 
		// when the onProject event is triggered. If not, some actions such as removeit, moveit and renameit
		// cannot be received. Instead, actions delete, move and rename will be used to trigger 
		// radiam component. The difference between action delete and removeit is that the delete action is
		// triggered when users open the delete ajax box, while the removeit action is triggered when users 
		// click on the Delete button. 
		if ($orderingFilesPlg > $orderingRadiamPlg)
		{
			$targetActions = array("save", "saveprov", "removeit", "moveit", "renameit", "savedir");
		}
		else 
		{
			$targetActions = array("save", "saveprov", "delete", "move", "rename", "savedir");
		}		

		// // Trigger Radiam Component if uploading, deleteing, moving or renaming files, creating new directories
		// if (in_array($action, $targetActions, true)) 
		// {
		// 	// FOR TESTING ACTION PURPOSE
		// 	// $sql_test = "INSERT INTO `#__radiam_radprojects` (`radiam_project_uuid`, `radiam_user_uuid`, `radiam_token`, `created`)
		// 	// 			 VALUES ('{$action}', '{$orderingFilesPlg}', '{$orderingRadiamPlg}', now())";
		// 	// $db->setQuery($sql_test);
		// 	// $db->execute(); 

		// 	// Update the last files event time
		// 	$sql = "UPDATE `#__radiam_radconfigs` SET `last_run`=now() WHERE `configname`='radiam_host_url'";
		// 	$db->setQuery($sql);
		// 	$db->execute();  
		// }

		// File actions
		if (in_array($action, $targetActions, true)) 
		{	
			// Update the last files event time
			$sql = "UPDATE `#__radiam_radconfigs` SET `last_run`=now() WHERE `configname`='radiam_host_url'";
			$this->db->setQuery($sql);
			$this->db->execute();  
			
			switch ($this->action)
			{
				case 'save':
					$this->_saveFile();
					break;
				case 'savedir':
					$this->_saveDir();
					break;
				case 'moveit':
					$this->_move();
					break;
				case 'removeit':
					$this->_delete();
					break;
				case 'renameit':
					$this->_rename();
					break;	
			}		
			// processQueue($this->projectId);
		}
		return;
	}

	/**
	 * Write to radiamqueue table for uploading file action
	 *
	 * @return  void  
	 */	
	protected function _saveFile()
	{	
		$json       = Request::getInt('json', 0);
		$no_html    = Request::getInt('no_html', 0);
		$ajaxUpload = $no_html && !$json ? true : false;

		
		// TODO: Get incoming file(s), refer to Repo.insert()
		if ($ajaxUpload)
		{	
			// Ajax upload
			if (isset($_FILES['qqfile']))
			{
				$file = $_FILES['qqfile']['name'];
			}
			elseif (isset($_GET['qqfile']))
			{
				$file = $_GET['qqfile'];
			}
			else
			{
				$file = "error";
			}
			$file = Filesystem::clean(trim($file));
			$path = $this->_getFullPath($file);
			
			// File existed, update it
			if (is_file($path)) {	
				// $this->action = 'update';
				$this->_updateFile($path);
			}
			else {
				$this->_writeToDb($this->projectId, $path, $this->_actionMapping[$this->action]);
			}
		}
		else
		{	
			// Regular upload
			$upload = Request::getArray('upload', '', 'files');
			for ($i=0; $i < count($upload['name']); $i++) {	
				$file = $upload['name'][$i];
				$file = Filesystem::clean(trim($file));
				$path = $this->_getFullPath($file);
				if (is_file($path)) {	
					// File existed, update it
					// $this->action = 'update';
					$this->_updateFile($path);
				}
				else {
					$this->_writeToDb($this->projectId, $path, $this->_actionMapping[$this->action]);
				}
			}
		}
	}

	/**
	 * Write to radiamqueue table for creating directory action
	 *
	 * @return  void  
	 */
	protected function _saveDir()
	{	
		$newDir = trim(Request::getString('newdir', ''));
		$newDirPath = $this->subdir ? $this->subdir . DS . $newDir : $newDir;
		
		if ($this->repo->dirExists($newDirPath))
		{
			return;
		}
		$path = $this->_getFullPath($newDir);
		$this->_writeToDb($this->projectId, $path, $this->_actionMapping[$this->action]);
	}

	/**
	 * Write to radiamqueue table for moving or deleting file(s) and folder(s) action
	 *
	 * @return  void  
	 */
	protected function _move()
	{	
		$items = $this->_sortIncoming(); 
		if (!empty($items))
		{
			foreach ($items as $element)
			{
				foreach ($element as $type => $item)
				{
					// Get type and item name
					break;
				} 

				// Must have a name
				if (trim($item) == '')
				{
					continue;
				}
				$srcPath = $this->_getFullPath($item);
				$srcLocalPath = $this->_getLocalPath($item);

				$newpath = trim(urldecode(Request::getString('newpath', '')), DS);
				$newdir  = Request::getString('newdir', '');
				$target  = $newdir ? $newdir : $newpath;
				$newItemPath = $target ? $target . DS . $item : $item; 
				$destPath = $this->path . DS. $newItemPath;

				if ($type === 'folder') {
					$itemsInDir = $this->repo->filelist(array(
						'subdir'		   => $srcLocalPath,
						'sortby'           => 'localpath',
						'showFullMetadata' => false,
						'getParents'       => true,
						'getChildren'      => true,
						'dirsOnly'         => false,
						'recursive'        => true
					));
					foreach ($itemsInDir as $i) {
						$this->_writeToDb($this->projectId, $i->get('fullPath'), $this->_actionMapping[$this->action], $destPath . DS . $i->get('name'));
					}
				}
				$this->_writeToDb($this->projectId, $srcPath, $this->_actionMapping[$this->action], $destPath);
			}
		}
	}

	/**
	 * Write to radiamqueue table for moving or deleting file(s) and folder(s) action
	 *
	 * @return  void  
	 */
	protected function _delete()
	{	
		$items = $this->_sortIncoming();
		if (!empty($items))
		{
			foreach ($items as $element)
			{
				foreach ($element as $type => $item)
				{
					// Get type and item name
					break;
				} 

				// Must have a name
				if (trim($item) == '')
				{
					continue;
				}
				$srcPath = $this->_getFullPath($item);
				$srcLocalPath = $this->_getLocalPath($item);
				if ($type === 'folder') {
					$itemsInDir = $this->repo->filelist(array(
						'subdir'		   => $srcLocalPath,
						'sortby'           => 'localpath',
						'showFullMetadata' => false,
						'getParents'       => true,
						'getChildren'      => true,
						'dirsOnly'         => false,
						'recursive'        => true
					));
					foreach ($itemsInDir as $i) {
						$this->_writeToDb($this->projectId, $i->get('fullPath'), $this->_actionMapping[$this->action]);
					}
				}
				$this->_writeToDb($this->projectId, $srcPath, $this->_actionMapping[$this->action]);
			}
		}
	}

	/**
	 * Write to radiamqueue table for renaming file(s) and folder(s) action
	 *
	 * @return  void  
	 */
	protected function _rename()
	{	
		$oldName = Request::getString('oldname', '');
		$newName = Request::getString('newname', '');
		$type = Request::getString('type', 'file');
		$srcPath = $this->_getFullPath($oldName);
		$destPath = $this->_getFullPath($newName);

		if (!empty($oldName)) {
			if ($type === 'folder') {
				$srcLocalPath = $this->_getLocalPath($oldName);
				$pos = strrpos($srcLocalPath, $oldName);
				if ($pos !== false) {
					$destLocalPath = substr_replace($srcLocalPath, $newName, $pos, strlen($oldName));
				}
				else {
					$destLocalPath = null;
				}
				$itemsInDir = $this->repo->filelist(array(
					'subdir'		   => $srcLocalPath,
					'sortby'           => 'localpath',
					'showFullMetadata' => false,
					'getParents'       => true,
					'getChildren'      => true,
					'dirsOnly'         => false,
					'recursive'        => true
				));
				foreach ($itemsInDir as $i) {
					// replace the first occurence of the oldName string
					$itemSrcLocalPath = $i->get('localPath');
					$pos = strpos($itemSrcLocalPath, $srcLocalPath);
					if ($pos !== false) {
						$itemDestLocalPath = substr_replace($itemSrcLocalPath, $destLocalPath, $pos, strlen($srcLocalPath));
					}
					else {
						$itemDestLocalPath = null;
					}
					$this->_writeToDb($this->projectId, $i->get('fullPath'), $this->_actionMapping[$this->action], $this->path . DS . $itemDestLocalPath);
				}
			}
			$this->_writeToDb($this->projectId, $srcPath, $this->_actionMapping[$this->action], $destPath);
		}
		else {
			return false;
		}
	}

	protected function _updateFile($path) 
	{
		// On the radiam server, the old file deleted and a new one with the same name created
		$this->_writeToDb($this->projectId, $path, 'delete');
		$this->_writeToDb($this->projectId, $path, 'create');
	}

	/**
	 * Helper function: insert record to the radiam queue table 
	 *
	 * @return  void  
	 */
	protected function _writeToDb($projectId, $srcPath, $action, $destPath=null)
	{	
		if ($destPath != null) {
			$sql_test = "INSERT INTO `#__radiam_radqueue` (`project_id`, `src_path`, `dest_path`, `action`, `created`)
						 VALUES ('{$projectId}', '{$srcPath}', '{$destPath}', '{$action}', now())";
		}
		else {
			$sql_test = "INSERT INTO `#__radiam_radqueue` (`project_id`, `src_path`, `action`, `created`)
						 VALUES ('{$projectId}', '{$srcPath}', '{$action}', now())";
		}
		$this->db->setQuery($sql_test);
		$this->db->execute();
	}

	/**
	 * Helper function: get full path of selected items (file or folder)
	 *
	 * @return  string   
	 */
	protected function _getFullPath($objectName) {
		if (empty($this->subdir)) 
		{
			$path = $this->path . DS. $objectName;	
		}
		else 
		{
			$path = $this->path . DS . $this->subdir . DS. $objectName;	
		}
		return $path;
	}

	/**
	 * Helper function: get relative path 
	 *
	 * @return  string   
	 */
	protected function _getLocalPath($objectName) {
		if (empty($this->subdir)) 
		{
			$path = $objectName;	
		}
		else 
		{
			$path = $this->subdir . DS. $objectName;	
		}
		return $path;
	}

	/**
	 * Helper function: sort selected file/folder data
	 *
	 * @return  array
	 */
	protected function _sortIncoming()
	{
		// Clean incoming data
		// $this->_cleanData();

		// Incoming
		$checked = Request::getArray('asset', array());
		$folders = Request::getArray('folder', array());

		$combined = array();
		if (!empty($checked) && is_array($checked))
		{
			foreach ($checked as $ch)
			{
				if (trim($ch) != '')
				{
					$combined[] = array('file' => urldecode($ch));
				}
			}
		}
		elseif ($file = Request::getString('asset', ''))
		{
			$combined[] = array('file' => urldecode($file));
		}

		// [!] Legacy support
		$files = Request::getArray('file', array());
		if (!empty($files) && is_array($files))
		{
			foreach ($files as $ch)
			{
				if (trim($ch) != '')
				{
					$combined[] = array('file' => urldecode($ch));
				}
			}
		}
		elseif ($file = Request::getString('file', ''))
		{
			$combined[] = array('file' => urldecode($file));
		}

		if (!empty($folders) && is_array($folders))
		{
			foreach ($folders as $f)
			{
				if (trim($f) != '')
				{
					$combined[] = array('folder' => urldecode($f));
				}
			}
		}
		elseif ($folder = Request::getString('folder', ''))
		{
			$combined[] = array('folder' => urldecode($folder));
		}

		return $combined;
	}
}

function processQueue($projectId)
{	
	
	$radiamQueue = getRadiamQueue($projectId);
	if ($radiamQueue !== false)
	{
		foreach ($radiamQueue as $event)
		{	
			
			$result = postToRadiamApi($event);
			$id = $event->id;
			if ($result === 'success')
			{	
				deleteRadiamQueueRow($id);
			}
			elseif ($result === 'fail')
			{
				updateRadiamQueue($id);
			}
		}
	}
}

function getRadiamQueue($projectId)
{
	$db = App::get('db');
	$sql = "SELECT `id`, `project_id`, `src_path`, `action`
			FROM `#__radiam_radqueue`
			WHERE `project_id` = '{$projectId}'
			ORDER BY `created` ASC";
	$db->setQuery($sql);
	$db->query();

	if (!$db->getNumRows())
	{
		return false;
	}

	$radiamQueue = $db->loadObjectList();
	return $radiamQueue;
}

function deleteRadiamQueueRow($id)
{
	$db = App::get('db');
	$sql = "DELETE FROM `#__radiam_radqueue`
			WHERE `id` = '{$id}'";
	$db->setQuery($sql);
	$db->query();
}

function updateRadiamQueue($id)
{
	$db = App::get('db');
	$sql = "UPDATE `#__radiam_radqueue`
			SET `last_modified` = now()
			WHERE `id` = '{$id}'";
	$db->setQuery($sql);
	$db->query();
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