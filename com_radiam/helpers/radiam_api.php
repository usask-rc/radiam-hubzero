<?php
namespace Components\Radiam\Helpers;


/**
 * Main Radiam API class
 */
class RadiamAPI
{
    function __construct($baseurl, $authtokens, $logger, $userId) {

        $this->tokenfile = null;
        $this->baseurl = $baseurl;
        $this->setLogger($logger);
        $this->authtokens = $authtokens;
        $this->userId = $userId;
        $this->headers = array(
            'Content-type: application/json',
            'Accept: application/json'
        );
        
        if (isset($this->baseurl)) {
            if (substr($this->baseurl, 0, 4) !== "http") {
                $this->baseurl = "http://" . $this->baseurl;
            }
            if (substr($this->baseurl, -1, 1) !== "/") {
                $this->baseurl = $this->baseurl . "/";
            }
            $this->endpoints = array(
                "login"        => $this->baseurl . "api/token/",
                "refresh"      => $this->baseurl . "api/token/refresh/",
                "users"        => $this->baseurl . "api/users/",
                "groups"       => $this->baseurl . "api/researchgroups/",
                "projects"     => $this->baseurl . "api/projects/",
                "locations"    => $this->baseurl . "api/locations/",
                "locationtypes"=> $this->baseurl . "api/locationtypes/",
                "useragents"   => $this->baseurl . "api/useragents/"
            );
        }
    }


    /**
     * Set up the logger
     *
     * @param   object?  $logger The logger
     */
    public function setLogger($logger) {
        $this->logger = $logger;
    }


    /**
     * Load auth tokens from a file
     *
     * @return  boolean?
     */
    public function loadAuthFromFile() {
        // TODO: uncomment and fix
        // if (file_exists($this->tokenfile)) {
        //     $this->authtokens = json_decode(file_get_contents($f), true);
        //     if array_key_exists("access", $this->authtokens) {
        //         return true;
        //     }
        // }
        return null;
    }


    /**
     * Log error messages
     *
     * @param  string  $message  The message to log
     */
    public function logError($message) {
        if (isset($this->logger)) {
            $this->logger->error($message);
        }
    }


    /**
     * Write auth tokens to database
     */
    public function writeAuthToDb() {

        $db = App::get('db');
        $sql = "UPDATE `#__radiam_radtokens` 
                SET `access_token` = '{$this->authtokens['access']}'
                WHERE `user_id` = '{$this->userId}';";

        $db->setQuery($sql);
        $db->query();
    }

    /**
     * Make login requests
     *
     * @param  string  $username  The username being used to log in
     * @param  string  $password  The password being used to log in
     * @return  boolean?
     */
    public function login($username, $password) {
        $body = array("username" => $username, "password" => $password);
        try {
            $jsonString = json_encode($body);
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
        if ($statusCode != 200) {
            return false;
        } else {
            $respObj = json_decode($result);
            if (isset($respObj->refresh))
                $this->authtokens["refresh"] = $respObj->refresh;
            if (isset($respObj->access))
                $this->authtokens["access"] = $respObj->access;
            if (isset($this->tokenfile))
                $this->writeAuthToDb();
            return true;
        }
    }


    /**
     * Refresh an auth token
     */
    public function refreshToken() {

        $body = array("refresh" => $this->authtokens["refresh"]);           
        $ch = curl_init($this->endpoints["refresh"]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Otherwise reponse=1
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($statusCode != 200) {
            $this->logError(sprintf("Unable to refresh auth token %s:\n%s\n", $statusCode, $result));
        } else {
            $respObj = json_decode($result);
            if ($respObj->access != null) {
                $this->authtokens["access"] = $respObj->access;
            }
        }
    }


    /**
     * Perform a GET call to the API
     *
     * @param  string  $url  The URL being called
     * @param  int  $retries  Number of retries to attempt, default 1
     * @return  array  $response  The response from the API
     */
    public function apiGet($url, $retries = 1) {
        if ($retries <= 0) {
            $this->logError("Ran out of retries to connect to Radiam API");
            $response = null;
            return $response;
        }
        $getHeaders = $this->headers;
        array_push($getHeaders, "Authorization: Bearer " . $this->authtokens["access"]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $getHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //otherwise 301 error
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode == 403) {
            $responseJson = json_decode($result);
            if ($responseJson->code == "token_not_valid") {
                $this->refreshToken();
                $this->writeAuthToDb();
                $response = $this->apiGet($url, ($retries - 1));
                return $response;
            } else {
                $this->logError(sprintf("Unauthorized request %s:\n%s\n", $statusCode, $result));
            }
        } 
        elseif ($statusCode == 200) {
            $response = json_decode($result);
            return $response;
        } 
        elseif ($statusCode == 429) {
            $responseJson = json_decode($result);
            if (property_exists($responseJson, "retry-after")) {
                sleep(((int) $responseJson->{"retry-after"}) + 1);    
            } else {
                sleep(4);
            }
            $this->apiGet($url, 1);
        } 
        else {
            $this->logError(sprintf("Radiam API error while getting from: %s with code %s and error %s \n", $url, $statusCode, $result));
            $response = null;
            return $response;
        }
    }


    /**
     * Perform a POST call to the API
     *
     * @param  string  $url  The URL being called
     * @param  string  $body  The body of the POST
     * @param  int  $retries  Number of retries to attempt, default 1
     * @return  array  $response  The response from the API
     */
    public function apiPost($url, $body, $retries = 1) {
        if ($retries <= 0) {
            $this->logError("Ran out of retries to connect to Radiam API");
            $response = null;
            return $response;
        }
        $postHeaders = $this->headers;
        array_push($postHeaders, "Authorization: Bearer " . $this->authtokens["access"]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Otherwise response=1
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
        curl_setopt($ch, CURLINFO_HEADER_OUT, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);

        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode == 403) {
            $responseJson = json_decode($result);
            if ($responseJson->code == "token_not_valid") {
                $this->refreshToken();
                $this->writeAuthToDb();
                $response = $this->apiPost($url, $body, ($retries - 1));
                return $response;
            } else {
                $this->logError(sprintf("Unauthorized request %s:\n%s\n", $statusCode, $result));
            }
        } elseif ($statusCode == 200 || $statusCode == 201) {
            $response = json_decode($result);
            return $response;
        } elseif ($statusCode == 429) {
            $responseJson = json_decode($result);
            if (property_exists($responseJson, "retry-after")) {
                sleep(((int) $responseJson->{"retry-after"}) + 1);    
            } else {
                sleep(4);
            }
            $this->apiPost($url, $body, 1);
        } else {
            $this->logError(sprintf("Radiam API error while getting from: %s with code %s and error %s \n", $url, $statusCode, $result));
            $response = null;
            return $response;
        }
    }
    /**
     * Perform a bulk POST call to the API
     *
     * @param  string  $url  The URL being called
     * @param  string  $body  The body of the POST
     * @param  int  $retries  Number of retries to attempt, default 1
     * @return  array  $response  The response from the API
     */
    public function apiPostBulk($url, $body, $retries = 1) {
        if ($retries <= 0) {
            $this->logError("Ran out of retries to connect to Radiam API");
            $response = array(null, false);
            return $response;
        }
        $postHeaders = $this->headers;
        array_push($postHeaders, "Authorization: Bearer " . $this->authtokens["access"]);
        $jsonString = json_encode($body);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $postHeaders);
        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode == 403) {
            $responseJson = json_decode($result);
            if ($responseJson->code == "token_not_valid") {
                $this->refreshToken();
                $this->writeAuthToDb();
                $response = $this->apiPostBulk($url, $body, ($retries - 1));
                return $response;
            } else {
                $this->logError(sprintf("Unauthorized request %s:\n%s\n", $statusCode, $result));
            }
        } elseif ($statusCode == 200 || $statusCode == 201) {
            $response = json_decode($result);
            return $response;
        } elseif ($statusCode == 429) {
            $responseJson = json_decode($result);
            if (property_exists($responseJson, "retry-after")) {
                sleep(((int) $responseJson->{"retry-after"}) + 1);    
            } else {
                sleep(4);
            }
            $this->apiPostBulk($url, $body, 1);
        } else {
            $this->logError(sprintf("Radiam API error while getting from: %s with code %s and error %s \n", $url, $statusCode, $result));
            $response = null;
            return $response;
        }
    }


    /**
     * Perform a DELETE call to the API
     *
     * @param  string  $url  The URL being called
     * @param  int  $retries  Number of retries to attempt, default 1
     * @return  array  $response  The response from the API
     */
    public function apiDelete($url, $retries = 1) {
        if ($retries <= 0) {
            $this->logError("Ran out of retries to connect to Radiam API");
            $response = null;
            return $response;
        }
        $deleteHeaders = $this->headers;
        array_push($deleteHeaders, "Authorization: Bearer " . $this->authtokens["access"]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $deleteHeaders);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //otherwise 301 error
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        
        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode == 403) {
            $responseJson = json_decode($result);
            if ($responseJson->code == "token_not_valid") {
                $this->refreshToken();
                $this->writeAuthToDb();
                $response = $this->apiDelete($url, ($retries - 1));
                return $response;
            } else {
                $this->logError(sprintf("Unauthorized request %s:\n%s\n", $statusCode, $result));
            }
        } elseif ($statusCode == 200 || $statusCode == 204) {
            $response = true;
            return $response;
        } elseif ($statusCode == 429) {
            $responseJson = json_decode($result);
            if (array_key_exists("retry-after", $responseJson)) {
                sleep(((int) $responseJson->{"retry-after"}) + 1);    
            } else {
                sleep(4);
            }
            $this->apiDelete($url, 1);
        } else {
            $this->logError(sprintf("Radiam API error while getting from: %s with code %s and error %s \n", $url, $statusCode, $result));
            $response = null;
            return $response;
        }
    }


    /**
     * Get list of users
     *
     * @return  array  $users  Output from users endpoint 
     */
    public function getUsers() {
        $users = $this->apiGet($this->endpoints["users"]);
        return $users;
    }


    /**
     * Get logged in user
     *
     * @return  array  $currentUser  Current user output from users endpoint 
     */
    public function getLoggedInUser() {
        $currentUser = $this->apiGet($this->endpoints["users"] . "current");
        return $currentUser;
    }


    /**
     * Get list of groups
     *
     * @return  array  $get_groups  Output from groups endpoint 
     */
    public function getGroups() {
        $groups = $this->apiGet($this->endpoints["groups"]);
        return $groups;
    }


    /**
     * Get list of users
     *
     * @return  array  $projects  Output from projects endpoint 
     */
    public function getProjects() {
        $projects = $this->apiGet($this->endpoints["projects"]);
        return $projects;
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
            return null;
        }
        // if (gettype($body) == "array") {
        //     $body = json_encode($body);
        // }
        $checkinPost = $this->apiPost($checkinUrl, $body);
        return $checkinPost;
    }


    /**
     * Create a project on the API
     *
     * @param  array  $body  JSON to post
     * @return  string  $createProjectPost  Result of constructed post
     */
    public function createProject($body) {
        if ($body == null) {
            return null;
        }
        // if (gettype($body) == "array") {
        //     $body = json_encode($body);
        // }
        $createProjectPost = $this->apiPost($this->endpoints["projects"], $body);
        return $createProjectPost;
    }


    /**
     * Create a location on the API
     *
     * @param  array  $body  JSON to post
     * @return  string  $createLocationPost  Result of constructed post
     */
    public function createLocation($body) {
        if ($body == null) {
            return null;
        }
        // if (gettype($body) == "array") {
        //     $body = json_encode($body);
        // }
        $createLocationPost = $this->apiPost($this->endpoints["locations"], $body);
        return $createLocationPost;
    }


    /**
     * Create a user agent on the API
     *
     * @param  array  $body  JSON to post
     * @return  string  $createUserAgentPost  Result of constructed post
     */
    public function createUserAgent($body) {
        if ($body == null) {
            return null;
        }
        // if (gettype($body) == "array") {
        //     $body = json_encode($body);
        // }
        $createUserAgentPost = $this->apiPost($this->endpoints["useragents"], $body);
        return $createUserAgentPost;
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
            return null;
        }
        $indexUrl .= "docs/";
        $createDocumentPost = $this->apiPost($indexUrl, $body);
        return $createDocumentPost;
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
            return null;
        }
        // if (gettype($body) == "array") {
        //     $body = json_encode($body);
        // }
        $indexUrl .= "docs/";
        $createDocumentPost = $this->apiPostBulk($indexUrl, $body);
        return $createDocumentPost;
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
            return null;
        }
        $indexUrl .= "docs/" . urldecode($id);
        $deletePost = $this->apiDelete($indexUrl);
        return $deletePost;
    }


    /**
     * Search an API endpoint using the path to a file
     *
     * @param  string  $indexUrl  Index URL to post to
     * @param  string  $path   Path to file you're searching for
     * @return  string  $pathSearch  Path search result
     */
    public function searchEndpointByPath($indexUrl, $path) {
        $pathSearch = $this->searchEndpointByFieldname($indexUrl, $path, "path.keyword");
        return $pathSearch;
    }


    /**
     * Search an API endpoint by a field name rather than a filepath
     *
     * @param  string  $indexUrl  Index URL to post to
     * @param  string  $target  Search target
     * @param  string  $fieldname  The name of the field being searched on
     * @return  string  $fieldSearch  The field search result
     */
    public function searchEndpointByFieldname($indexUrl, $target, $fieldname) {
        if ($fieldname == null) {
            $this->logError($target . " field name is missing for endpoint search");
            return null;
        }
        if ($target == null) {
            $this->logError($fieldname . " argument is missing for endpoint search");
            return null;
        }
        $indexUrl .= "search/";
        $body = array("query" => array("bool" => array("filter" => array("term" => array($fieldname=>$target)))));
        $fieldnameSearchPost = $this->apiPost($indexUrl, $body);
        return $fieldnameSearchPost;
    }


    /**
     * Search an API endpoint by name
     *
     * @param  string  $endpoint  The endpoint address
     * @param  string  $name  The name
     * @param  string  $namefield  The field being searched on, e.g. "name"
     * @return  array  $getEndpointUrl  The endpoint response
     */
    public function searchEndpointByName($endpoint, $name, $namefield = "name") {
        if ($name == null) {
            $this->logError("Name argument is missing for endpoint search");
            return null;
        }
        if (substr($endpoint, 0, 4) === "http") {
            $endpointUrl = $endpoint;
        } else {
            if (isset($this->endpoints[$endpoint])) {
                $endpointUrl = $this->endpoints[$endpoint];
            } else {
                $this->logError($endpoint . " is neither an endpoint URL nor a well known endpoint");
                return null;
            }
        }
        $endpointUrl .= "?" . $namefield . "=" . $name;
        $getEndpointUrl = $this->apiGet($endpointUrl);
        return $getEndpointUrl;
    }


    /**
     * Get the API status code
     *
     * @param  string  The API URL
     * @param  int  Retry attempts
     * @return  int  API HTTP response status code
     */
    public function apiGetStatusCode($url, $retries = 1) {
        if ($retries <= 0) {
            $this->logError("Ran out of retries");
            return null;
        }
        $get_headers = $this->headers;
        array_push($get_headers, "Authorization: Bearer " . $this->authtokens["access"]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $get_headers);
        $result = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($statusCode == 403) {
            $responseJson = json_decode($result);
            if ($responseJson->code == "token_not_valid") {
                $this->refreshToken();
                $this->writeAuthToDb();
                return $this->apiGet($url, ($retries - 1));
            } else {
                $this->logError(sprintf("Unauthorized request %s:\n%s\n", $statusCode, $result));
            }
        } else {
            return $statusCode;
        }
    }
}
