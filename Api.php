<?php
//-----------------------------------------------------------------------------
// Api.php
//-----------------------------------------------------------------------------

/**
 * AppNexus API base class.
 *
 * @package AppNexus
 * @author Moiz Merchant <moiz@exactdrive.com>
 * @version $Id$
 */
class AppNexus_Api
{

    //-------------------------------------------------------------------------
    // constants
    //-------------------------------------------------------------------------

    const GET    = 'GET';
    const POST   = 'POST';
    const PUT    = 'PUT';
    const DELETE = 'DELETE';

    /**
     * No error.
     */
    const OK = "OK";

    /**
     * The user is not logged in, or the login credentials are invalid.
     */
    const ERR_NOAUTH = "NOAUTH";

    /**
     * The user's password has expired and needs to be reset.
     */
    const ERR_NOAUTH_EXPIRED = "NOAUTH_EXPIRED";

    /**
     * The user's account has been deactivated.
     */
    const ERR_NOAUTH_DISABLED = "NOAUTH_DISABLED";

    /**
     * The user is not authorized to take the requested action.
     */
    const ERR_UNAUTH = "UNAUTH";

    /**
     * The syntax of the request is incorrect.
     */
    const ERR_SYNTAX = "SYNTAX";

    /**
     * A system error has occurred.
     */
    const ERR_SYSTEM = "SYSTEM";

    /**
     * A client request is inconsistent; for example, a request attempts to
     *  delete a default creative attached to an active placement.
     */
    const ERR_INTEGRITY = "INTEGRITY";

    //-------------------------------------------------------------------------
    // static fields
    //-------------------------------------------------------------------------

    /**
     * AppNexus Api username.
     *
     * @var string
     */
    private static $_userName;

    /**
     * AppNexus Api password.
     *
     * @var string
     */
    private static $_password;

    /**
     * AppNexus Api base url.
     *
     * @var string
     */
    private static $_baseUrl;

    /**
     * AppNexus Api authentication token ('token) + creation time ('created').
     *
     * @var array
     */
    private static $_token;

    //-------------------------------------------------------------------------
    // static properties
    //-------------------------------------------------------------------------

    /**
     * Set the AppNexus Api base url.
     *
     * @param string $url
     */
    public static function setBaseUrl($url)
    {
        // make sure any extra characters are removed
        self::$_baseUrl = rtrim(rtrim($url), "/\\");
    }

    //-------------------------------------------------------------------------

    /**
     * Get the AppNexus Api base url.
     *
     * @return string
     * @throws Exception
     */
    public static function getBaseUrl()
    {
        if (!self::$_baseUrl) {
            throw new Exception('AppNexus url was not set.');
        }

        return self::$_baseUrl;
    }

    //-------------------------------------------------------------------------

    /**
     * Set the AppNexus Api password.
     *
     * @param string $password
     */
    public static function setPassword($password)
    {
        self::$_password = $password;
    }

    //-------------------------------------------------------------------------

    /**
     * Get the AppNexus Api password.
     *
     * @return string
     * @throws Exception
     */
    public static function getPassword()
    {
        if (!self::$_password) {
            throw new Exception('AppNexus password was not set.');
        }

        return self::$_password;
    }

    //-------------------------------------------------------------------------

    /**
     * Set the AppNexus Api username.
     *
     * @param string $userName
     */
    public static function setUserName($userName)
    {
        self::$_userName = $userName;
    }

    //-------------------------------------------------------------------------

    /**
     * Get the AppNexus Api username.
     *
     * @return string
     * @throws Exception
     */
    public static function getUserName()
    {
        if (!self::$_userName) {
            throw new Exception('AppNexus username was not set.');
        }

        return self::$_userName;
    }

    //-------------------------------------------------------------------------
    // static methods
    //-------------------------------------------------------------------------

    /**
     * Make curl request to AppNexus Api, raw result will be returned with no
     *  validation or json decoding.
     *
     * @param  string $url
     * @param  string $type
     * @param  array  $data
     * @return array  $response
     */
    protected static function makeRequestRaw($url, $type = AppNexus_Api::GET,
        $data = null)
    {
        // spit out debug info to app nexus logs
        AppNexus_Monolog::addInfo("Url: $url");
        AppNexus_Monolog::addInfo('Data: ' . $data);

        // grab authentication token
        $token = self::_getAuthenticationToken();

        // make request
        $result = self::_makeRequest($token, $url, $type, $data);
        return $result;
    }

    //-------------------------------------------------------------------------
    /**
     * Make curl request to AppNexus Api.
     *
     * @param  string $url
     * @param  string $type
     * @param  array  $data
     * @return array  $response
     */
    protected static function makeRequest($url, $type = AppNexus_Api::GET,
        $data = null)
    {
        // spit out debug info to app nexus logs
        AppNexus_Monolog::addInfo("Url: $url");
        AppNexus_Monolog::addInfo('Data: ' . json_encode($data));

        // grab authentication token
        $token = self::_getAuthenticationToken();

        // make request
        $result = self::_makeRequest($token, $url, $type, $data);

        // convert to hash and validate response
        $json   = json_decode($result, true);
        $status = self::_isValid($json);
        if ($status == self::ERR_NOAUTH) {
            AppNexus_Monolog::addInfo('Token expired, re-authorizing...');

            // request a new token, the old one is bad/expired
            $token  = self::_getAuthenticationToken(true);

            // re-run the result
            $result = self::_makeRequest($token, $url, $type, $data);
            $json   = json_decode($result, true);
            $status = self::_isValid($json);
        }

        // Throw Exception if API response is invalid
        // For debugging purposes, we need to send the API call data as well as
        // the result. We serialize to store in the database
        if ($status != self::OK) {
            // $errorMsg = "Invalid AppNexus Response ($url): $requestParams => $result";
            $apiCallData['data'][] = json_encode($data);
            $apiCallData['response'][] = json_encode($json['response']);
            throw new Exception(serialize($apiCallData));
        }

        // add result to logs...
        AppNexus_Monolog::addInfo($result);

        return $json['response'];
    }

    //-------------------------------------------------------------------------
    // internal methods
    //-------------------------------------------------------------------------

    /**
     * Check that the data returned from AppNexus is valid and no errors
     *  were found.
     *
     * @param  array $json
     * @return bool
     * @throws Exception
     */
    private static function _isValid($json)
    {
        // validate input is of the correct type
        if (!is_array($json)) {
            $errorMsg = "Invalid type passed into Api::isValid method, " .
                        "expected array, received " . gettype($json) . ".";
            BookingEngine_Debug::printr($errorMsg, false);
            BookingEngine_Debug::printr($json);
            AppNexus_Monolog::addInfo($errorMsg);
            throw new Exception($errorMsg);
        }

        // validate json
        if (!array_key_exists("response", $json)) {
            $errorMsg = "Invalid AppNexus response.";
            BookingEngine_Debug::printr($errorMsg, false);
            BookingEngine_Debug::printr($json);
            AppNexus_Monolog::addInfo($errroMsg);
            throw new Exception($errorMsg);
        }

        // check error status
        $response = $json['response'];
        if (array_key_exists("error", $response)) {
            $errorMsg = "AppNexus query recieved an error response.";
            AppNexus_Monolog::addInfo($errorMsg);
            AppNexus_Monolog::addInfo($response['error']);
            return $response['error_id'];
        }

        // response is valid
        return self::OK;
    }

    //-------------------------------------------------------------------------

    /**
     * Request new authorization token for AppNexus Api.
     *
     * @return string $token
     */
    private static function _requestAuthenticationToken()
    {
        // spit out debug info to app nexus logs
        AppNexus_Monolog::addInfo('Requesting AppNexus Api token...');

        // compile authorization url
        $url = self::getBaseUrl() . '/auth';

        // compile login json
        $auth = array(
            "auth" => array(
                "username" => self::getUserName(),
                "password" => self::getPassword(),
            )
        );

        // set default curl options
        $curlOptions = array(
            CURLOPT_VERBOSE        => false,
            CURLOPT_URL            => $url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($auth)
        );

        // execute the curl request
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $result = curl_exec($curl);
        AppNexus_Monolog::addInfo($result);

        // convert to hash and validate response
        $json   = json_decode($result, true);
        $status = self::_isValid($json);
        if ($status != self::OK) {
            $errorMsg = "AppNexus authorization failed with: $status!";
            BookingEngine_Debug::printr($errorMsg, false);
            BookingEngine_Debug::printr($json);
            throw new Exception($errorMsg);
        }

        // return the token
        return $json['response']['token'];
    }

    //-------------------------------------------------------------------------

    /**
     * Grab authorization token from database.
     *
     * @return string $token
     */
    private static function _getAuthenticationToken($force = false)
    {
        // cache token from database
        if (!self::$_token) {
            $model        = new AppNexus_Model_DbTable_AppNexus();
            $row          = $model->find(1)->current();
            self::$_token = $row->toArray();
        }

        // request a new token if older than 2 hours
        if (AppNexus_Model_DbTable_AppNexus_Row::
            isMoreThanTwoHoursOld(self::$_token['created']) ||
            (self::$_token['token'] == "")                  ||
            $force) {

            // request a new token from AppNexus
            $token = self::_requestAuthenticationToken();

            // cache in database
            $model = new AppNexus_Model_DbTable_AppNexus();
            $row   = $model->find(1)->current();
            $row->saveToken($token);

            // cache locally
            self::$_token = $row->toArray();
        }

        return self::$_token['token'];
    }

    //-------------------------------------------------------------------------

    /**
     * Make curl request to AppNexus Api.
     *
     * @param  string $url
     * @param  string $type
     * @param  string $token
     * @param  hash   $data
     * @return hash   $result
     */
    private static function _makeRequest($token, $url,
        $type = AppNexus_Api::GET, $data = null)
    {
        // set default curl options
        $curlOptions = array(
            CURLOPT_VERBOSE        => false,
            CURLOPT_URL            => $url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array("Authorization: $token")
        );

        // configure curl POST
        if ($type == AppNexus_Api::POST) {
            $curlOptions[CURLOPT_POST] = true;
            if ($data) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                array_push($curlOptions[CURLOPT_HTTPHEADER], 'Content-Length: 0');
            }

        // configure curl PUT
        } else if ($type == AppNexus_Api::PUT) {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = AppNexus_Api::PUT;
            if ($data) {
               $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
               array_push($curlOptions[CURLOPT_HTTPHEADER], 'Content-Length: 0');
            }

        // configure curl DELETE
        } else if ($type == AppNexus_Api::DELETE) {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = AppNexus_Api::DELETE;
        }

        // execute the curl request
        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);
        $result = curl_exec($curl);

        return $result;
    }

}