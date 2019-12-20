<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

namespace Components\Radiam\Site\Controllers;

use Components\Radiam\Models\Comment;
use Components\Radiam\Models\Entry;
use Components\Radiam\Models\Files;
use Components\Radiam\Models\Project;
use Components\Radiam\Models\Projects;
use Components\Radiam\Models\Radtoken;
use Hubzero\Component\SiteController;
use Hubzero\Utility\String;
use Hubzero\Utility\Sanitize;
use Exception;
use Document;
use Request;
use Pathway;
use Event;
use Lang;
use Route;
use User;
use Date;

/**
 * Blog controller class for entries
 */
class Radiam extends SiteController
{
    const FILES_PATH = "search/";
    const PROJECTS_API = "/api/projects/";

    /**
     * Determines task being called and attempts to execute it
     *
     * @return  void
     */
    public function execute()
    {
        $this->_authorize();
        $this->_authorize('entry');
        $this->_authorize('comment');

        $this->registerTask('new', 'edit');
        $this->registerTask('login', 'login');

        $this->registerTask('groups', 'groups');
        $this->registerTask('locations', 'locations');
        $this->registerTask('projects', 'projects');
        $this->registerTask('users', 'users');

        parent::execute();
    }


    public function loginTask()
    {
        $filters = array(
            'search'     => Request::getVar('search', ''),
            'authorized' => false,
            'state'      => 1,
            'access'     => User::getAuthorisedViewLevels()
        );

        $username = Request::getString('username', null, 'post');
        $password = Request::getString('passwd', null, 'post');

        if (!User::isGuest())
        {
            $token = Radtoken::oneOrNew(User::get('id'));
            if ($token != null && !$token->expired())
            {
                $this->redirect($url='display', null, null);
            }

        }

        if ($username != null && $password != null)
        {
            $token = Radtoken::get_token($this, $username, $password);
            // $this->redirect($url='display', null, null);
            // TODO delete this
            $this->view
                ->set('config', $this->config)
                ->set('filters', $filters);

            if ($token->error != "") {
                $this->view->setError($token->errorMsg);
            }

            $this->view->display();
        }
        else
        {
            // Output HTML
            $this->view
                ->set('config', $this->config)
                ->set('filters', $filters)
                ->display();
        }
    }

    public function getUsers($access_token, $radiam_url) {
        return $this->getJsonFromRadiamApi($access_token, $radiam_url, "/api/users/");
    }

    public function getProjects($access_token, $radiam_url) {
        $projectsJson = $this->getJsonFromRadiamApi($access_token, $radiam_url, self::PROJECTS_API);
        return new Projects($projectsJson);
    }

    public function getLocations($access_token, $radiam_url) {
        $locationsJson = $this->getJsonFromRadiamApi($access_token, $radiam_url, "/api/locations/");
        return $locationsJson;
    }

    public function getFiles($accessToken, $radiamUrl, $projectId, $query) {
        $filesJson = $this->getJsonFromRadiamApi($accessToken, $radiamUrl, self::PROJECTS_API . $projectId . "/" . self::FILES_PATH, $query);
        return new Files($filesJson);
    }

    /**
     * Get a php StdObject with the contents of the Json returned from the Radiam api
     *
     * $access_token - an oauth access token to use to access the API
     * $radiam_url - the url to contact for the api
     * $path - the path of the api to use
     * $query - an array of query string parameters to add to the request
    **/
    public function getJsonFromRadiamApi($access_token, $radiam_url, $path, $query=array()) {
        $header[] = "Authorization: Bearer " . $access_token;

        $url = $radiam_url . $path;
        if (isset($query)) {
            $url = $url . "?" . http_build_query($query);
        }

        // echo "URL: '" . $url . "'\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $output = curl_exec($ch);
        $err = curl_error($ch);

        curl_close($ch);

        if ($err) {
            echo "cURL Error #:" . $err;
            return false;
        } else {
            $json = json_decode($output);
            return $json;
        }
    }

    public function getToken()
    {
        $token = false;

        if(User::isGuest())
        {
            return false;
        }

        $token = Radtoken::oneOrNew(User::get('id'));

        if ($token == null)
        {
            return false;
        }

        if ($token->expired())
        {
            if (!$token->refresh($this))
            {
                return false;
            }
            else
            {
                return $token;
            }
        }
        else
        {
            return $token;
        }
    }

     public function projectsTask()
    {
        $token = $this->getToken();
        if (!$token)
        {
            $this->redirect($url='login', null, null);
        }

        $radiam_url = $this->config->get('radiamurl', null);

        $users = $this->getUsers($token->get('access_token'), $radiam_url);

        $this->view
            ->set('config', $this->config)
            ->set('filters', null)
            ->set('users', $users)
            ->display();

    }

    public function locationsTask()
    {
        $token = $this->getToken();
        if (!$token)
        {
            $this->redirect($url='login', null, null);
        }

        $radiam_url = $this->config->get('radiamurl', null);

        $users = $this->getUsers($token->get('access_token'), $radiam_url);

        $this->view
            ->set('config', $this->config)
            ->set('filters', null)
            ->set('users', $users)
            ->display();

    }

    public function groupsTask()
    {
        $token = $this->getToken();
        if (!$token)
        {
            $this->redirect($url='login', null, null);
        }

        $radiam_url = $this->config->get('radiamurl', null);

        $users = $this->getUsers($token->get('access_token'), $radiam_url);

        $this->view
            ->set('config', $this->config)
            ->set('filters', null)
            ->set('users', $users)
            ->display();

    }

    public function getRadiamURL()
    {
        return $this->config->get('radiamurl', null);
    }

    public function usersTask()
    {
        $token = $this->getToken();
        if (!$token)
        {
            $this->redirect($url='login', null, null);
        }

        $radiam_url = $this->getRadiamURL();

        $users = $this->getUsers($token->get('access_token'), $radiam_url);

        $this->view
            ->set('config', $this->config)
            ->set('filters', null)
            ->set('users', $users)
            ->display();

    }

    /**
     * Convert hubzero style pages (start 0, pagesize 20, start 20, pagesize 20) to Radiam API
     * style pages (start = 1, pagesize = 20, start = 2, pagesize = 20)
     */
    public function getRadiamPage($start, $limit) {
        if ($start <= 0) {
            return 1;
        }
        return floor($start / $limit) + 1;
    }

    public function displayTask()
    {
        // Filters for returning results
        $filters = array(
            'start'       => Request::getInt('start', 0),
            'limit'      => Request::getInt('limit', 20),
            'search'     => Request::getVar('search', ''),
            'project'     => Request::getVar('project', ''),
            'authorized' => false,
            'state'      => 1,
            'access'     => User::getAuthorisedViewLevels()
        );

        $token = $this->getToken();
        if (!$token)
        {
            $this->redirect($url='login', null, null);
        }

        if ($this->config->get('access-manage-component'))
        {
            //$filters['state'] = null;
            $filters['authorized'] = true;
            array_push($filters['access'], 5);
        }

        $projects = $this->getProjects($token->get('access_token'), $this->getRadiamURL());
        if ($projects->count > 0) {
            $query = array();
            if (isset($filters['search'])) {
                $query["q"] = $filters['search'];
            }

            $query["page"] = $this->getRadiamPage($filters["start"], $filters["limit"]);
            $query["page_size"] = $filters["limit"];

            if (isset($filters["project"])) {
                $foundProject = false;
                foreach ($projects->projects as &$project) {
                    if ($project->id == $filters["project"]) {
                        $foundProject = true;
                    }
                }
                if (!$foundProject) {
                    $filters["project"] = $projects->projects[0]->id;
                }

            } else {
                $filters["project"] = $projects->projects[0]->id;
            }

            $files = $this->getFiles($token->get('access_token'), $this->getRadiamURL(), $filters["project"], $query);
        } else {
            $files = new Files();
        }

        $pagination = new \Hubzero\Pagination\Paginator($files->count, $filters["start"], $filters["limit"]);

        // Need to set any extra parameters for the pagination links to maintain the state
        if (isset($filters['search'])) {
            $pagination->setAdditionalUrlParam('search', $filters['search']);
        }

        $pagination->setAdditionalUrlParam('project', $filters["project"]);

        // Output HTML
        $this->view
            ->set('config', $this->config)
            ->set('filters', $filters)
            ->set('projects', $projects)
            ->set('files', $files)
            ->set('pagination', $pagination)
            ->display();

    }

    public function automaticUserId()
    {
        return (int)User::get('id', 0);
    }


    /**
     * Display an entry
     *
     * @return  void
     */
    public function entryTask()
    {
        $alias = Request::getVar('alias', '');

        if (!$alias)
        {
            App::redirect(
                Route::url('index.php?option=' . $this->_option . '&controller=' . $this->_controller)
            );
            return;
        }

        // Load entry
        $row = Entry::oneByScope(
            $alias,
            $this->model->get('scope'),
            $this->model->get('scope_id')
        );

        if (!$row->get('id') || $row->isDeleted())
        {
            App::abort(404, Lang::txt('COM_RADIAM_NOT_FOUND'));
        }

        // Check authorization
        if (!$row->access('view'))
        {
            App::abort(403, Lang::txt('COM_RADIAM_NOT_AUTH'));
        }

        // Filters for returning results
        $filters = array(
            'limit'      => 10,
            'start'      => 0,
            'scope'      => 'site',
            'scope_id'   => 0,
            'authorized' => false,
            'state'      => Entry::STATE_PUBLISHED,
            'access'     => User::getAuthorisedViewLevels()
        );

        if (!User::isGuest())
        {
            if ($this->config->get('access-manage-component'))
            {
                $filters['authorized'] = true;
            }
        }

        // Check session if this is a newly submitted entry. Trigger a proper event if so.
        if (Session::get('newsubmission.radiam')) {
            // Unset the new submission session flag
            Session::set('newsubmission.radiam');
            Event::trigger('content.onAfterContentSubmission', array('Radiam'));
        }

        // Output HTML
        $this->view
            ->set('config', $this->config)
            ->set('row', $row)
            ->set('filters', $filters)
            ->setLayout('entry')
            ->display();
    }

    /**
     * Show a form for editing an entry
     *
     * @param   object  $entry
     * @return  void
     */
    public function editTask($entry = null)
    {
        if (User::isGuest())
        {
            $rtrn = Request::getVar('REQUEST_URI', Route::url('index.php?option=' . $this->_option . '&task=' . $this->_task, false, true), 'server');
            App::redirect(
                Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn)),
                Lang::txt('COM_RADIAM_LOGIN_NOTICE'),
                'warning'
            );
            return;
        }

        if (!$this->config->get('access-create-entry')
         && !$this->config->get('access-edit-entry')
         && !$this->config->get('access-manage-entry'))
        {
            App::abort(403, Lang::txt('COM_RADIAM_NOT_AUTH'));
        }

        if (!is_object($entry))
        {
            $entry = Entry::oneOrNew(Request::getInt('entry', 0));
        }

        if ($entry->isNew())
        {
            $entry->set('allow_comments', 1);
            $entry->set('state', Entry::STATE_PUBLISHED);
            $entry->set('scope', 'site');
            $entry->set('created_by', User::get('id'));
        }

        foreach ($this->getErrors() as $error)
        {
            $this->view->setError($error);
        }

        $this->view
            ->set('config', $this->config)
            ->set('entry', $entry)
            ->setLayout('edit')
            ->display();
    }

    /**
     * Save entry
     *
     * @return  void
     */
    public function saveTask()
    {
        if (User::isGuest())
        {
            $rtrn = Request::getVar('REQUEST_URI', Route::url('index.php?option=' . $this->_option . '&task=' . $this->_task), 'server');
            App::redirect(
                Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn)),
                Lang::txt('COM_RADIAM_LOGIN_NOTICE'),
                'warning'
            );
            return;
        }

        if (!$this->config->get('access-create-entry')
         && !$this->config->get('access-edit-entry')
         && !$this->config->get('access-manage-entry'))
        {
            App::abort(403, Lang::txt('COM_RADIAM_NOT_AUTH'));
        }

        // Check for request forgeries
        Request::checkToken();

        $fields = Request::getVar('entry', array(), 'post', 'none', 2);

        // Make sure we don't want to turn off comments
        //$fields['allow_comments'] = (isset($fields['allow_comments'])) ? 1 : 0;

        if (isset($fields['publish_up']) && $fields['publish_up'] != '')
        {
            $fields['publish_up']   = Date::of($fields['publish_up'], Config::get('offset'))->toSql();
        }
        if (isset($fields['publish_down']) && $fields['publish_down'] != '')
        {
            $fields['publish_down'] = Date::of($fields['publish_down'], Config::get('offset'))->toSql();
        }
        $fields['scope'] = 'site';
        $fields['scope_id'] = 0;

        $row = Entry::oneOrNew($fields['id'])->set($fields);

        // Trigger before save event
        $isNew  = $row->isNew();
        $result = Event::trigger('onRadiamBeforeSave', array(&$row, $isNew));

        if (in_array(false, $result, true))
        {
            Notify::error($row->getError());
            return $this->editTask($row);
        }

        // Store new content
        if (!$row->save())
        {
            Notify::error($row->getError());
            return $this->editTask($row);
        }

        // Process tags
        if (!$row->tag(Request::getVar('tags', '')))
        {
            Notify::error($row->getError());
            return $this->editTask($row);
        }

        // Trigger after save event
        Event::trigger('onRadiamAfterSave', array(&$row, $isNew));

        // Log activity
        Event::trigger('system.logActivity', [
            'activity' => [
                'action'      => ($fields['id'] ? 'updated' : 'created'),
                'scope'       => 'radiam.entry',
                'scope_id'    => $row->get('id'),
                'description' => Lang::txt('COM_RADIAM_ACTIVITY_ENTRY_' . ($fields['id'] ? 'UPDATED' : 'CREATED'), '<a href="' . Route::url($row->link()) . '">' . $row->get('title') . '</a>'),
                'details'     => array(
                    'title' => $row->get('title'),
                    'url'   => Route::url($row->link())
                )
            ],
            'recipients' => [
                $row->get('created_by')
            ]
        ]);

        // If the new resource is published, set the session flag indicating the new submission
        if ($isNew)
        {
            Session::set('newsubmission.radiam', true);
        }

        // Redirect to the entry
        App::redirect(
            Route::url($row->link())
        );
    }

    /**
     * Mark an entry as deleted
     *
     * @return  void
     */
    public function deleteTask()
    {
        if (User::isGuest())
        {
            $rtrn = Request::getVar('REQUEST_URI', Route::url('index.php?option=' . $this->_option, false, true), 'server');
            App::redirect(
                Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn)),
                Lang::txt('COM_RADIAM_LOGIN_NOTICE'),
                'warning'
            );
            return;
        }

        if (!$this->config->get('access-delete-entry')
         && !$this->config->get('access-manage-entry'))
        {
            App::abort(403, Lang::txt('COM_RADIAM_NOT_AUTH'));
        }

        // Incoming
        $id = Request::getInt('entry', 0);

        if (!$id)
        {
            return $this->displayTask();
        }

        $process    = Request::getVar('process', '');
        $confirmdel = Request::getVar('confirmdel', '');

        // Initiate a blog entry object
        $entry = Entry::oneOrFail($id);

        // Did they confirm delete?
        if (!$process || !$confirmdel)
        {
            if ($process && !$confirmdel)
            {
                $this->setError(Lang::txt('COM_RADIAM_ERROR_CONFIRM_DELETION'));
            }

            foreach ($this->getErrors() as $error)
            {
                $this->view->setError($error);
            }

            $this->view
                ->set('config', $this->config)
                ->set('entry', $entry)
                ->display();
            return;
        }

        // Check for request forgeries
        Request::checkToken();

        // Delete the entry itself
        $entry->set('state', 2);

        if (!$entry->save())
        {
            Notify::error($entry->getError());
        }

        // Log the activity
        Event::trigger('system.logActivity', [
            'activity' => [
                'action'      => 'deleted',
                'scope'       => 'radiam.entry',
                'scope_id'    => $id,
                'description' => Lang::txt('COM_RADIAM_ACTIVITY_ENTRY_DELETED', '<a href="' . Route::url($entry->link()) . '">' . $entry->get('title') . '</a>'),
                'details'     => array(
                    'title' => $entry->get('title'),
                    'url'   => Route::url($entry->link())
                )
            ],
            'recipients' => [
                $entry->get('created_by')
            ]
        ]);

        // Return the entries lsit
        App::redirect(
            Route::url('index.php?option=' . $this->_option)
        );
    }

    /**
     * Save a comment
     *
     * @return  void
     */
    public function savecommentTask()
    {
        // Ensure the user is logged in
        if (User::isGuest())
        {
            $rtrn = Request::getVar('REQUEST_URI', Route::url('index.php?option=' . $this->_option), 'server');
            App::redirect(
                Route::url('index.php?option=com_users&view=login&return=' . base64_encode($rtrn)),
                Lang::txt('COM_RADIAM_LOGIN_NOTICE'),
                'warning'
            );
            return;
        }

        // Check for request forgeries
        Request::checkToken();

        // Incoming
        $data = Request::getVar('comment', array(), 'post', 'none', 2);

        // Instantiate a new comment object and pass it the data
        $comment = Comment::oneOrNew($data['id'])->set($data);

        // Trigger before save event
        $isNew  = $comment->isNew();
        $result = Event::trigger('onRadiamCommentBeforeSave', array(&$comment, $isNew));

        if (in_array(false, $result, true))
        {
            $this->setError($comment->getError());
            return $this->entryTask();
        }

        // Store new content
        if (!$comment->save())
        {
            $this->setError($comment->getError());
            return $this->entryTask();
        }

        // Trigger after save event
        Event::trigger('onRadiamCommentAfterSave', array(&$comment, $isNew));

        // Log the activity
        $entry = Entry::oneOrFail($comment->get('entry_id'));

        $recipients = array($comment->get('created_by'));
        if ($comment->get('created_by') != $entry->get('created_by'))
        {
            $recipients[] = $entry->get('created_by');
        }
        if ($comment->get('parent'))
        {
            $recipients[] = $comment->parent()->get('created_by');
        }

        Event::trigger('system.logActivity', [
            'activity' => [
                'action'      => ($data['id'] ? 'updated' : 'created'),
                'scope'       => 'radiam.entry.comment',
                'scope_id'    => $comment->get('id'),
                'anonymous'   => $comment->get('anonymous', 0),
                'description' => Lang::txt('COM_RADIAM_ACTIVITY_COMMENT_' . ($data['id'] ? 'UPDATED' : 'CREATED'), $comment->get('id'), '<a href="' . Route::url($entry->link() . '#c' . $comment->get('id')) . '">' . $entry->get('title') . '</a>'),
                'details'     => array(
                    'title'    => $entry->get('title'),
                    'entry_id' => $entry->get('id'),
                    'url'      => $entry->link()
                )
            ],
            'recipients' => $recipients
        ]);

        return $this->entryTask();
    }

    /**
     * Method to check admin access permission
     *
     * @param   string   $assetType
     * @param   integer  $assetId
     * @return  void
     */
    protected function _authorize($assetType='component', $assetId=null)
    {
        $this->config->set('access-view-' . $assetType, true);

        if (!User::isGuest())
        {
            $asset  = $this->_option;
            if ($assetId)
            {
                $asset .= ($assetType != 'component') ? '.' . $assetType : '';
                $asset .= ($assetId) ? '.' . $assetId : '';

                if ($assetType != 'component')
                {
                    $at .= '.' . $assetType;
                }
            }

            $at = '';
            /*if ($assetType != 'component')
            {
                $at .= '.' . $assetType;
            }*/

            // Admin
            $this->config->set('access-admin-' . $assetType, User::authorise('core.admin', $asset));
            $this->config->set('access-manage-' . $assetType, User::authorise('core.manage', $asset));
            // Permissions
            $this->config->set('access-create-' . $assetType, User::authorise('core.create' . $at, $asset));
            $this->config->set('access-delete-' . $assetType, User::authorise('core.delete' . $at, $asset));
            $this->config->set('access-edit-' . $assetType, User::authorise('core.edit' . $at, $asset));
            $this->config->set('access-edit-state-' . $assetType, User::authorise('core.edit.state' . $at, $asset));
            $this->config->set('access-edit-own-' . $assetType, User::authorise('core.edit.own' . $at, $asset));
        }
    }
}
