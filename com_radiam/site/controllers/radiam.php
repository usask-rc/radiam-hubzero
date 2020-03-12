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
use Components\Radiam\Helpers\RadiamHelper;
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
    const GROUPS_API = "/api/researchgroups/";
    private $token = null;

    /**
     * Determines task being called and attempts to execute it
     *
     * @return  void
     */
    public function execute()
    {
        $this->_authorize();
        $this->_loadConfig();

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
            $token = Radtoken::one(User::get('id'));
            if ($token !== false && !$token->expired($this))
            {
                $dashboardUrl = 'index.php?option=com_members&id=' . User::get('id') . '&active=dashboard';
                $this->redirect($dashboardUrl, Lang::txt('You have already logined on Radiam.'), null);
                
            }
            else if($token === false) {
                if ($username != null && $password != null)
                {   
                    $this->config->set('clientid', $username);
                    $this->config->set('clientsecret', $password);
    
                    $token = Radtoken::get_token($this, $username, $password);
                    $dashboardUrl = 'index.php?option=com_members&id=' . User::get('id') . '&active=dashboard';
                    $this->redirect($dashboardUrl, Lang::txt('Login to Radiam successfully'), null);
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
            else {
                $token->refresh($this);
                // $this->redirect($url='display', null, null);   
            }
        }
        else {        
            // Output HTML
            $this->view
                 ->set('config', $this->config)
                 ->set('filters', $filters)
                 ->display();
        }        
    }

    public function getUsers($access_token, $radiam_url) {
        // TODO Replace with proper constant / config
        return $this->getJsonFromRadiamApi($access_token, $radiam_url, "/api/users/");
    }

    public function getProjects($access_token, $radiam_url) {
        // TODO Replace with proper constant / config
        $projectsJson = $this->getJsonFromRadiamApi($access_token, $radiam_url, self::PROJECTS_API);
        return new Projects($projectsJson);
    }

    public function getLocations($access_token, $radiam_url) {
        // TODO Replace with proper constant / config
        $locationsJson = $this->getJsonFromRadiamApi($access_token, $radiam_url, "/api/locations/");
        return $locationsJson;
    }

    public function getFiles($accessToken, $radiamUrl, $projectId, $query) {
        $filesJson = $this->postJsonFromRadiamApi($accessToken, $radiamUrl, self::PROJECTS_API . $projectId . "/" . self::FILES_PATH, $query);
        return new Files($filesJson);
    }

    public function getGroups($access_token, $radiam_url) {
        // TODO Replace with proper constant / config
        return $this->getJsonFromRadiamApi($access_token, $radiam_url, self::GROUPS_API);
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

        $url = RadiamHelper::buildUrl($radiam_url, $path);
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

    /**
     * Post a php StdObject with the contents of the Json returned from the Radiam api
     *
     * $access_token - an oauth access token to use to access the API
     * $radiam_url - the url to contact for the api
     * $path - the path of the api to use
     * $query - an array of query string parameters to add to the request
    **/
    public function postJsonFromRadiamApi($access_token, $radiam_url, $path, $query=array()) {
        $header[] = "Authorization: Bearer " . $access_token;

        $url = RadiamHelper::buildUrl($radiam_url, $path);
        if (isset($query)) {
            $url = $url . "?" . http_build_query($query);
        }

        // echo "URL: '" . $url . "'\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        // curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $output = curl_exec($ch);
        $err = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

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

        $token = Radtoken::one(User::get('id'));
        if ($token === false)
        {
            return false;
        }

        if ($token->expired($this))
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

        $radiam_url = $this->getRadiamURL();

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

        $radiam_url = $this->getRadiamURL();

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

        $radiam_url = $this->getRadiamURL();

        $groups = $this->getGroups($token->get('access_token'), $radiam_url);

        $this->view
            ->set('config', $this->config)
            ->set('filters', null)
            ->set('groups', $groups)
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
    protected function _loadConfig()
    {
        // Radiam Config     
        $db = App::get('db');
        $sql = "SELECT `configvalue` FROM `#__radiam_radconfigs` WHERE `configname` LIKE '%radiam%url%'";
        $db->setQuery($sql);
        $radiam_url = $db->loadObject()->configvalue;
        $this->config->set('radiamurl', $radiam_url);
    }
}
