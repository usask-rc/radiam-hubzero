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
use stdClass;

/**
 * Blog controller class for entries
 */
class Radiam extends SiteController
{
    const FILES_PATH = "search/";
    const PROJECTS_API = "/api/projects/";
    const GROUPS_API = "/api/researchgroups/";
    const LOCATIONS_API = "/api/locations/";
    const USERS_API = "/api/users/";
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

        $this->registerTask('login', 'login');

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
                $this->redirect($dashboardUrl, Lang::txt('You have already logged in to Radiam.'), null);
                
            }
            else if($token === false) {
                if ($username != null && $password != null)
                {   
                    $this->config->set('clientid', $username);
                    $this->config->set('clientsecret', $password);
    
                    $token = Radtoken::get_token($this, $username, $password);
                    $this->view
                        ->set('config', $this->config)
                        ->set('filters', $filters);
    
                    if ($token->error != "") {
                        $this->view->setError($token->errorMsg);
                        $this->view->display();
                    }
                    else {
                        $dashboardUrl = 'index.php?option=com_members&id=' . User::get('id') . '&active=dashboard';
                        $this->redirect($dashboardUrl, Lang::txt('Login to Radiam successfully'), null);
                    }
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

    public function getProjects($access_token, $radiam_url) {
        $projectsJson = $this->getJsonFromRadiamApi($access_token, $radiam_url, self::PROJECTS_API);
        return new Projects($projectsJson);
    }

    public function getLocation($access_token, $radiam_url, $locationId) {
        $locationJson = $this->getJsonFromRadiamApi($access_token, $radiam_url, self::LOCATIONS_API . $locationId);
        return $locationJson;
    }

    public function getFiles($accessToken, $radiamUrl, $projectId, $query) {
        if (isset($query["q"]) and $query["q"] != null) {
            // for search action, two api calls will be operated, one for target files, one for target directories
            // after receiving these two responses, we need to combine the results for further dispaly
            $qFilesbody = array("query" => array("bool" => array("filter" => array(array("term" => array("type" => "file"))), 
                                                                 "should" => array(array("query_string" => array("query" => "*".$query["q"]."*"))),
                                                                 "minimum_should_match" => 1)));
            $qFilesJson = $this->postJsonFromRadiamApi($accessToken, $radiamUrl, self::PROJECTS_API . $projectId . "/" . self::FILES_PATH, $query, $qFilesbody);
            $qDirsbody = array("query" => array("bool" => array("filter" => array(array("term" => array("type" => "directory"))), 
                                                                 "should" => array(array("query_string" => array("query" => "*".$query["q"]."*"))),
                                                                 "minimum_should_match" => 1)));
            $qDirsJson = $this->postJsonFromRadiamApi($accessToken, $radiamUrl, self::PROJECTS_API . $projectId . "/" . self::FILES_PATH, $query, $qDirsbody);
            $filesJson = new StdClass;
            $qFilesCount = isset($qFilesJson->count) ? $qFilesJson->count : 0;
            $qDirsCount = isset($qDirsJson->count) ? $qDirsJson->count : 0;  
            $filesJson->count = $qFilesCount + $qDirsCount;
            $qFilesResults = isset($qFilesJson->results) ? $qFilesJson->results : array();
            $qDirsResults = isset($qDirsJson->results) ? $qDirsJson->results : array();
            $filesJson->results = array_merge($qFilesResults, $qDirsResults);
        }
        else {
            $filesJson = $this->postJsonFromRadiamApi($accessToken, $radiamUrl, self::PROJECTS_API . $projectId . "/" . self::FILES_PATH, $query);
        }
        $locationsArray = array();
        foreach ($filesJson->results as $fileJson) {
            $locationId = $fileJson->location;
            $locationJson = $this->getLocation($accessToken, $radiamUrl, $locationId);
            if (isset($locationJson) && isset($locationJson->display_name)) {
                $locationsArray[$fileJson->id] = $locationJson->display_name;
            }
        }
        return new Files($filesJson, $locationsArray);
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
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //otherwise 301 error
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
    public function postJsonFromRadiamApi($access_token, $radiam_url, $path, $query=null, $body=null) {
        $headers = array(
            'Content-type: application/json',
            'Accept: application/json'
        );
        array_push($headers, "Authorization: Bearer " . $access_token);

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

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body != null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

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

    public function getRadiamURL()
    {   
        return $this->config->get('radiamurl', null);
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
