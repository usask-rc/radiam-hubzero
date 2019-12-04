<?php

namespace Components\Radiam\Models;

use Hubzero\Database\Relational;
use Hubzero\Utility\String;
use Hubzero\Config\Registry;
use Hubzero\Form\Form;
use Filesystem;
use Component;
use Lang;
use User;
use Date;

/**
 * Model class for an oauth 2 token
 */
class Radtoken extends Relational
{
    /**
     * The table namespace
     *
     * @var string
     */
    protected $namespace = 'radiam';

    /**
     * Default order by for model
     *
     * @var string
     */
    public $orderBy = 'user_id';

    /**
     * Default order direction for select queries
     *
     * @var  string
     */
    public $orderDir = 'desc';

    /**
     * Fields and their validation criteria
     *
     * @var  array
     */
    protected $rules = array(
        'user_id' => 'notempty',
        'access_token' => '',
        'refresh_token' => '',
        'valid_until' => ''
    );

    public $error = "";
    public $errorMsg = "";

    /**
     * Automatically fillable fields
     *
     * @var  array
     */
    public $always = array(
        'user_id'
    );

    /**
     * Automatic fields to populate every time a row is created
     *
     * @var  array
     */
    public $initiate = array(
    );

    /**
     * Fields to be parsed
     *
     * @var array
     */
    protected $parsed = array(
    );

    /**
     * Registry
     *
     * @var  object
     */
    public $params = null;

    /**
     * Scope adapter
     *
     * @var  object
     */
    protected $adapter = null;

    /**
     * Sets up additional custom rules
     *
     * @return  void
     */
    public function setup()
    {
    }

    /**
     * Has the access token expired
     *
     * @return  boolean
     */
    public function expired()
    {
        // If it doesn't exist or isn't published
        if ($this->isNew())
        {
            return true;
        }

        if (empty($this->get('access_token')))
        {
            return true;
        }

        if (empty($this->get('valid_until')))
        {
            return true;
        }

        if ($this->get('valid_until')
         && $this->get('valid_until') != '0000-00-00 00:00:00'
         && $this->get('valid_until') <= Date::toSql())
        {
            return true;
        }

        return false;
    }

    /**
     * Retrieve a property from the internal item object
     *
     * @param   string  $key  Property to retrieve
     * @return  string
     */
    public function item($key='')
    {
        return $this->adapter()->item($key);
    }

    /**
     * Delete the record and all associated data
     *
     * @return  boolean  False if error, True on success
     */
    public function destroy()
    {
        // Can't delete what doesn't exist
        if ($this->isNew())
        {
            return true;
        }

        // Attempt to delete the record
        return parent::destroy();
    }

    /**
     * Validates the set data attributes against the model rules
     *
     * @return  bool
     **/
    public function validate()
    {
        $valid = parent::validate();

        if ($valid)
        {
            $results = \Event::trigger('content.onContentBeforeSave', array(
                'com_radiam.entry.content',
                &$this,
                $this->isNew()
            ));

            foreach ($results as $result)
            {
                if ($result === false)
                {
                    $this->addError(Lang::txt('Content failed validation.'));
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    /**
     * Transforms a namespace to an object
     *
     * @return  object  An an object holding the namespace data
     */
    public function toObject()
    {
        $data = parent::toObject();

        $this->access();
        $data->params = $this->params->toObject();

        return $data;
    }

    /**
     * Get a form
     *
     * @return  object
     */
    public function getForm()
    {
        $file = __DIR__ . '/forms/' . strtolower($this->getModelName()) . '.xml';
        $file = Filesystem::cleanPath($file);

        $form = new Form('radiam', array('control' => 'fields'));

        if (!$form->loadFile($file, false, '//form'))
        {
            $this->addError(Lang::txt('JERROR_LOADFILE_FAILED'));
        }

        $data = $this->toArray();
        $data['params'] = $this->params->toArray();

        $form->bind($data);

        return $form;
    }

    public function automaticUserId()
    {
        return (int)User::get('id', 0);
    }

    public function automaticId($attributes)
    {
        return (int)User::get('id', 0);
    }

    //TODO use common codebase to make request to refresh token
    public function refresh($that)
    {
        $debug = false;
        $user_id = User::get('id');

        $radiam_url = $that->config->get('radiamurl', null);
        $client_id = $that->config->get('clientid', null);
        $client_secret = $that->config->get('clientsecret', null);


        if ($this->expired())
        {
            if ($debug)
            {
                echo("The token is being refreshed");
            }

            $data = array(
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->get('refresh_token')
            );


            // REST API Token URL
            // TODO Replace with proper constant / config
            $token_url = $radiam_url . "/api/oauth/token/";

            if ($debug)
            {
                echo "<div>Url: " . $radiam_url . "</div>";
                echo "<div>Client ID: " . $client_id . "</div>";
                echo "<div>Client Secret:" . $client_secret . "</div>";
                echo "<div>Token URL:" . $token_url . "</div>";
            }

            // cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $token_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
            curl_setopt($ch, CURLINFO_HEADER_OUT, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            $output = curl_exec($ch);
            $err = curl_error($ch);

            curl_close($ch);

            if ($err) {
                echo "cURL Error #:" . $err;
                return false;
            } else {
                $json = json_decode($output);
                if (isset($json -> {"access_token"})) {
                    if ($debug)
                    {
                        echo "<div>Access Token: " . $json->{"access_token"} . "</div>";
                        echo "<div>Refresh Token: " . $json->{"refresh_token"} . "</div>";
                        echo "<div>Expires In: " . date("c", (time() + $json->{"expires_in"})) . "</div>";
                    }

                    $this->set('access_token', $json->{"access_token"})
                        ->set('refresh_token', $json->{"refresh_token"})
                        ->set('valid_until', date("c", (time() + $json->{"expires_in"})))
                        ->save();
                    return true;
                }
                else {
                    if ($debug)
                    {
                        echo "<div>Raw: ";
                        var_dump($output);
                        echo "</div>";
                        echo "<div>JSON: ";
                        var_dump($json);
                        echo "</div>";
                    }
                    return false;
                }
            }
        }
        else
        {
            if ($debug)
            {
                echo("The token is not new");
            }
            return true;
        }

    }

    public static function get_token($that, $username, $password)
    {
        $debug = false;
        $user_id = User::get('id');

        $radiam_url = $that->config->get('radiamurl', null);
        $client_id = $that->config->get('clientid', null);
        $client_secret = $that->config->get('clientsecret', null);


        $token = Radtoken::oneOrNew($user_id);
        if ($token->isNew())
        {
            if ($debug)
            {
                echo("The token is new");
            }
            $data = array(
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password
            );

            // REST API Token URL
            $token_url = $radiam_url . "/api/oauth/token/";

            if ($debug)
            {
                echo "<div>Url: " . $radiam_url . "</div>";
                echo "<div>Client ID: " . $client_id . "</div>";
                echo "<div>Client Secret:" . $client_secret . "</div>";
                echo "<div>Token URL:" . $token_url . "</div>";
            }

            // cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $token_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
            curl_setopt($ch, CURLINFO_HEADER_OUT, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            $output = curl_exec($ch);
            $err = curl_error($ch);

            curl_close($ch);

            if ($err) {
                echo "cURL Error #:" . $err;
                return false;
            } else {
                $json = json_decode($output);
                if (isset($json->{"error"})) {
                    $token->error = $json->{"error"};
                    $token->errorMsg = $json->{"error_description"};
                    return $token;
                } else if (isset($json -> {"access_token"})) {
                    if ($debug)
                    {
                        echo "<div>Access Token: " . $json->{"access_token"} . "</div>";
                        echo "<div>Refresh Token: " . $json->{"refresh_token"} . "</div>";
                        echo "<div>Expires In: " . date("c", (time() + $json->{"expires_in"})) . "</div>";
                    }

                    $token->set('access_token', $json->{"access_token"})
                        ->set('refresh_token', $json->{"refresh_token"})
                        ->set('valid_until', date("c", (time() + $json->{"expires_in"})))
                        ->save();
                    return $token;
                }
                else {
                    if ($debug)
                    {
                        echo "<div>Raw: ";
                        var_dump($output);
                        echo "</div>";
                        echo "<div>JSON: ";
                        var_dump($json);
                        echo "</div>";
                    }
                    return false;
                }
            }
        }
        else
        {
            if ($debug)
            {
                echo("The token is not new");
            }
            return $token;
        }

    }
}
