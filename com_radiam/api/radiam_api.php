<?php
// Not sure about the namespace stuff
// namespace Components\Radiam\Api;


/**
 * Main API class
 */
class Radiam
{
    private static function init() {
        if (!$didInit) {
            $didInit = true;
            $this->logger = null;
            $this->tokenfile = null;
            $this->baseurl = "http://nginx";
            $this->headers = array(
                "Content-Type" => "application/json",
                "Accept" => "application/json"
            );
            $this->authtokens = [];
            # TODO: are we handling multiple arbitrary kv pairs here still?
            # for key, value in kwargs.items():
                # setattr(self, key, value)
            if (isset($this->baseurl)) {
                if (!startsWith($this->baseurl, "http")) {
                    $this->baseurl = "http://" . $this->baseurl;
                }
                $this->endpoints = array(
                    "login"=> $this->baseurl . "/api/token/",
                    "refresh"=> $this->baseurl . "/api/token/refresh/",
                    "users"=> $this->baseurl . "/api/users/",
                    "groups"=> $this->baseurl . "/api/researchgroups/",
                    "projects"=> $this->baseurl . "/api/projects/",
                    "locations"=> $this->baseurl . "/api/locations/",
                    "locationtypes"=> $this->baseurl . "/api/locationtypes/",
                    "useragents"=> $this->baseurl . "/api/useragents/"
                )
            }
        }
    }


    Radiam::init();


    /**
     * Set up the logger
     *
     * @param   object?  $logger The logger
     */
    public function setLogger($query) {
        $this->logger = logger;
    }


    /**
     * Load auth tokens from a file
     *
     * @return  boolean?
     */
    public function loadAuthFromFile() {
        if file_exists($this->tokenfile) {
            $this->authtokens = json_decode(file_get_contents($f), true);
            if array_key_exists("access", $this->authtokens) {
                return true;
            }
        }
        return null;
    }


    /**
     * Log error messages
     *
     * @param  string  $message  The message to log
     */
    public function log($message) {
        if (isset($this->logger)) {
            # TODO: send error message, not sure what php library we're using here
        }
    }


    /**
     * Write auth tokens to a file
     *
     * @param  string  $authfile  The authfile path, if it exists
     */
    public function writeAuthToFile($authfile = null) {
        if (!isset($authfile)) {
            $authfile = $this->tokenfile;
        }
        $fp = fopen($authfile, 'w');
        fwrite($fp, json_encode($this->authtokens));
        fclose($fp);
    }


    /**
     * Make login requests
     *
     * @param  string  $username  The username being used to log in
     * @param  string  $password  The password being used to log in
     * @return  boolean?
     */
    public function login($username, $password) {
        $body = array("username" => $username, "password" => $password)
        try {
            $jsonString = json_encode($body)
            $ch = curl_init($this->endpoints->login);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonString);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            $result = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } catch ( \Exception $e ) {
            return false;
        }
        if (statusCode != 200) {
            return false;
        } else {
            $respObj = json_decode($result, true);
            if (isset($respObj->refresh)):
                $this->authtokens["refresh"] = respObj["refresh"];
            if (isset($respObj->access)):
                $this->authtokens["access"] = respObj["access"];
            if (isset($this->tokenfile)):
                $this->writeAuthToFile();
            return true;
        }
    }


    /**
     * Refresh an auth token
     */
    public function refreshToken() {
        if (!isset($this->osf)) {
            $body = array("refresh" => $this->authtokens["refresh"])
            $jsonString = json_encode($body)
            $ch = curl_init($this->endpoints->refresh);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonString);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            $result = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (statusCode != 200) {
                # TODO: embed status code and response text in log string
                $this->log("Unable to refresh auth token")
            } else {
                $this->writeAuthToFile()
                $respObj = json_decode($result, true);
                if ($respObj->access != null) {
                    $this->authtokens["access"] = respObj["access"];
                }
            }
        } else {
            $ch = curl_init($this->baseurl . "/api/useragents/" . $this->useragent . "/token/new");
            $result = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (statusCode != 200) {
                # TODO: embed status code and response text in log string
                $this->log("Unable to refresh auth token")
            } else {
                $respObj = json_decode($result, true);
                if ($respObj->access != null) {
                    $this->authtokens["access"] = respObj["access"];
                }
            }
        }
    }


    # TODO: api_get
    # TODO: api_post
    # TODO: api_post_bulk
    # TODO: api_delete


    /**
     * Get list of users
     *
     * @return  array  $users  Output from users endpoint 
     */
    public function getUsers() {
        $users = $this->api_get($this->endpoints["users"]);
        return $users
    }


    /**
     * Get logged in user
     *
     * @return  array  $currentUser  Current user output from users endpoint 
     */
    public function getLoggedInUser() {
        $currentUser = $this->api_get($this->endpoints["users"] . "current");
        return $currentUser
    }


    /**
     * Get list of groups
     *
     * @return  array  $get_groups  Output from groups endpoint 
     */
    public function getGroups() {
        $users = $this->api_get($this->endpoints["groups"]);
        return $groups
    }


    /**
     * Get list of users
     *
     * @return  array  $projects  Output from projects endpoint 
     */
    public function getProjects() {
        $users = $this->api_get($this->endpoints["projects"]);
        return $projects
    }


    /**
     * Check in to API as agent to set up IDs
     *
     * @param  array  $body  JSON to post
     * @param  string  $checkinUrl  URL to check in to
     * @return  string  $checkinPost  Result of constructed post
     */
    public function agentCheckin($body, $checkinUrl) {
        if ($body == null) {
            return null
        }
        if (gettype($body) == "array") {
            $body = json_encode($body)
        }
        $checkinPost = $this->api_post($checkinUrl, $body);
        return $checkinPost
    }


    /**
     * Create a project on the API
     *
     * @param  array  $body  JSON to post
     * @return  string  $createProjectPost  Result of constructed post
     */
    public function createProject($body) {
        if ($body == null) {
            return null
        }
        if (gettype($body) == "array") {
            $body = json_encode($body)
        }
        $createProjectPost = $this->api_post($this->endpoints["projects"], $body);
        return $createProjectPost
    }


    /**
     * Create a location on the API
     *
     * @param  array  $body  JSON to post
     * @return  string  $createLocationPost  Result of constructed post
     */
    public function createLocation($body) {
        if ($body == null) {
            return null
        }
        if (gettype($body) == "array") {
            $body = json_encode($body)
        }
        $createLocationPost = $this->api_post($this->endpoints["locations"], $body);
        return $createLocationPost
    }


    /**
     * Create a user agent on the API
     *
     * @param  array  $body  JSON to post
     * @return  string  $createUserAgentPost  Result of constructed post
     */
    public function createUserAgent($body) {
        if ($body == null) {
            return null
        }
        if (gettype($body) == "array") {
            $body = json_encode($body)
        }
        $createUserAgentPost = $this->api_post($this->endpoints["useragents"], $body);
        return $createUserAgentPost
    }


    /**
     * Create a document on the API
     *
     * @param  string  $indexUrl  Index URL to post to
     * @param  array  $body  JSON to post
     * @return  string  $createDocumentPost  Result of constructed post
     */
    public function createDocument($indexUrl, $body) {
        if ($body == null) {
            return null
        }
        if (gettype($body) == "array") {
            $body = json_encode($body)
        }
        $indexUrl .= "docs/"
        $createDocumentPost = $this->api_post($indexUrl, $body);
        return $createDocumentPost
    }


    /**
     * Create multiple documents on the API
     *
     * @param  string  $indexUrl  Index URL to post to
     * @param  array  $body  JSON to post
     * @return  string  $createDocumentPost  Result of constructed post
     */
    public function createDocumentBulk($indexUrl, $body) {
        if ($body == null) {
            return null
        }
        if (gettype($body) == "array") {
            $body = json_encode($body)
        }
        $indexUrl .= "docs/"
        $createDocumentPost = $this->api_post_bulk($indexUrl, $body);
        return $createDocumentPost
    }


    /**
     * Delete a document on the API
     *
     * @param  string  $indexUrl  Index URL to post to
     * @param  string  $id  Document ID
     * @return  string  $deletePost  Result of constructed post
     */
    public function deleteDocument($indexUrl, $id) {
        if ($id == null) {
            return null
        }
        $index_url .= "docs/" . urldecode($id)
        $deletePost = $this->api_post($indexUrl);
        return $deletePost
    }


    /**
     * Search an API endpoint using the path to a file
     *
     * @param  string  $indexUrl  Index URL to post to
     * @param  string  $path   Path to file you're searching for
     * @return  string  $pathSearch  Patch search result
     */
    public function searchEndpointByPath($indexUrl, $path) {
        $pathSearch = $this->searchEndpointByFieldname($index_url, $path, "path.keyword")
        return $pathSearch
    }


    # TODO: search_endpoint_by_fieldname
    # TODO: search_endpoint_by_name
    # TODO: api_get_statusCode
}
