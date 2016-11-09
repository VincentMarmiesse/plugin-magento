<?php

/**
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Connector_Model_Connector
{
    /**
     * @var string connector version
     */
    const VERSION = '1.0';

    /**
     * @var string URL of the API Lengow
     */
    // const LENGOW_API_URL = 'http://api.lengow.io:80';
    // const LENGOW_API_URL = 'http://api.lengow.net:80';
    const LENGOW_API_URL = 'http://api.lengow.rec:80';
    // const LENGOW_API_URL = 'http://10.100.1.82:8081';

    /**
     * @var string URL of the SANDBOX Lengow
     */
    const LENGOW_API_SANDBOX_URL = 'http://api.lengow.net:80';

    /**
     * Default options for curl.
     */
    public static $curlOpts = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_USERAGENT      => 'lengow-php-sdk',
    );

    /**
     * @var mixed error returned by the API
     */
    public $error;

    /**
     * @var string the access token to connect
     */
    protected $_accessToken;

    /**
     * @var string the secret to connect
     */
    protected $_secret;

    /**
     * @var string temporary token for the authorization
     */
    protected $_token;

    /**
     * @var integer ID account
     */
    protected $_accountId;

    /**
     * @var integer the user Id
     */
    protected $_userId;

    /**
     * @var string
     */
    protected $_request;

    /**
     * @var array lengow url for curl timeout
     */
    protected $_lengowUrls = array (
        '/v3.0/orders'          => 15,
        '/v3.0/orders/actions/' => 15,
        '/v3.0/marketplaces'    => 10,
        '/v3.0/subscriptions'   => 5,
        '/v3.0/stats'           => 5,
        '/v3.0/cms'             => 5,
    );

    /**
     * Make a new Lengow API Connector.
     *
     * @param varchar $access_token Your access token.
     * @param varchar $secret Your secret.
     */
    public function init($access_token, $secret)
    {
        $this->_accessToken = $access_token;
        $this->_secret = $secret;
    }

    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Connectection to the API
     *
     * @param varchar $user_token The user token if is connected
     *
     * @return mixed array [authorized token + account_id + user_id] or false
     */
    public function connect($user_token = '')
    {
        $data = $this->_callAction(
            '/access/get_token',
            array(
                'access_token' => $this->_accessToken,
                'secret'       => $this->_secret,
                'user_token'   => $user_token
            ),
            'POST'
        );
        if (isset($data['token'])) {
            $this->_token = $data['token'];
            $this->_accountId = $data['account_id'];
            $this->_userId = $data['user_id'];
            return $data;
        } else {
            return false;
        }
    }

    /**
     * The API method.
     *
     * @param varchar $method Lengow method API call.
     * @param varchar $array Lengow method API parameters
     * @param varchar $type type of request GET|POST|PUT|HEAD|DELETE|PATCH
     * @param varchar $format return format of API
     *
     * @return array The formated data response
     */
    public function call($method, $array = array(), $type = 'GET', $format = 'json', $body = '')
    {
        try {
            $this->connect();
            if (!array_key_exists('account_id', $array)) {
                $array['account_id'] = $this->_accountId;
            }
            $data = $this->_callAction($method, $array, $type, $format, $body);
        } catch (Lengow_Connector_Model_Exception $e) {
            return $e->getMessage();
        }
        return $data;
    }

    /**
     * Get API call
     *
     * @param string $method Lengow method API call
     * @param array  $array  Lengow method API parameters
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    public function get($method, $array = array(), $format = 'json', $body = '')
    {
        return $this->call($method, $array, 'GET', $format, $body);
    }

    /**
     * Post API call
     *
     * @param string $method Lengow method API call
     * @param array  $array  Lengow method API parameters
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    public function post($method, $array = array(), $format = 'json', $body = '')
    {
        return $this->call($method, $array, 'POST', $format, $body);
    }

    /**
     * Head API call
     *
     * @param string $method Lengow method API call
     * @param array  $array  Lengow method API parameters
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    public function head($method, $array = array(), $format = 'json', $body = '')
    {
        return $this->call($method, $array, 'HEAD', $format, $body);
    }

    /**
     * Put API call
     *
     * @param string $method Lengow method API call
     * @param array  $array  Lengow method API parameters
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    public function put($method, $array = array(), $format = 'json', $body = '')
    {
        return $this->call($method, $array, 'PUT', $format, $body);
    }

    /**
     * Delete API call
     *
     * @param string $method Lengow method API call
     * @param array  $array  Lengow method API parameters
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    public function delete($method, $array = array(), $format = 'json', $body = '')
    {
        return $this->call($method, $array, 'DELETE', $format, $body);
    }

    /**
     * Patch API call
     *
     * @param string $method Lengow method API call
     * @param array  $array  Lengow method API parameters
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    public function patch($method, $array = array(), $format = 'json', $body = '')
    {
        return $this->call($method, $array, 'PATCH', $format, $body);
    }

    /**
     * Call API action
     *
     * @param string $api    Lengow method API call
     * @param array  $args   Lengow method API parameters
     * @param string $type   type of request GET|POST|PUT|HEAD|DELETE|PATCH
     * @param string $format return format of API
     * @param string $body
     *
     * @return array The formated data response
     */
    private function _callAction($api, $args, $type, $format = 'json', $body = '')
    {
        $result = $this->_makeRequest($type, $api, $args, $this->_token, $body);
        return $this->_format($result, $format);
    }

    /**
     * Get data in specific format
     *
     * @param mixed  $data
     * @param string $format
     *
     * @return array The formated data response
     */
    private function _format($data, $format)
    {
        switch ($format) {
            case 'json':
                return json_decode($data, true);
            case 'csv':
                return $data;
            case 'xml':
                return simplexml_load_string($data);
            case 'stream':
                return $data;
        }
    }

    /**
     * Make Curl request
     *
     * @param string $type  Lengow method API call
     * @param string $url   Lengow API url
     * @param array  $args  Lengow method API parameters
     * @param string $token temporary access token
     * @param string $body
     *
     * @return array The formated data response
     */
    protected function _makeRequest($type, $url, $args, $token, $body = '')
    {
        // Define CURLE_OPERATION_TIMEDOUT for old php versions
        defined("CURLE_OPERATION_TIMEDOUT") || define("CURLE_OPERATION_TIMEDOUT", CURLE_OPERATION_TIMEOUTED);
        $helper = Mage::helper('lengow_connector/data');
        $ch = curl_init();
        // Options
        $opts = self::$curlOpts;
        // get special timeout for specific Lengow API
        if (array_key_exists($url, $this->_lengowUrls)) {
            $opts[CURLOPT_TIMEOUT] = $this->_lengowUrls[$url];
        }
        // get url for a specific environment
        $url = self::LENGOW_API_URL.$url;
        $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($type);
        $url = parse_url($url);
        $opts[CURLOPT_PORT] = $url['port'];
        $opts[CURLOPT_HEADER] = false;
        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_VERBOSE] = false;
        if (isset($token)) {
            $opts[CURLOPT_HTTPHEADER] = array(
                'Authorization: '.$token
            );
        }
        $url = $url['scheme'].'://'.$url['host'].$url['path'];
        switch ($type) {
            case "GET":
                $opts[CURLOPT_URL] = $url.'?'.http_build_query($args);
                $helper->log(
                    'Connector',
                    $helper->setLogMessage('log.connector.call_api', array('curl_url' => $opts[CURLOPT_URL]))
                );
                break;
            case "PUT":
                $opts[CURLOPT_HTTPHEADER] = array_merge(
                    $opts[CURLOPT_HTTPHEADER],
                    array(
                        'Content-Type: application/json',
                        'Content-Length: '.strlen($body)
                    )
                );
                $opts[CURLOPT_URL] = $url.'?'.http_build_query($args);
                $opts[CURLOPT_POSTFIELDS] = $body;
                break;
            default:
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = count($args);
                $opts[CURLOPT_POSTFIELDS] = http_build_query($args);
                break;
        }
        // Exectute url request
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $errorNumber = curl_errno($ch);
        $errorText = curl_error($ch);
        if (in_array($errorNumber, array(CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED))) {
            $timeout = $helper->setLogMessage('lengow_log.exception.timeout_api');
            $errorMessage = $helper->setLogMessage(
                'log.connector.error_api',
                array('error_code' => $helper->decodeLogMessage($timeout, 'en_GB'))
            );
            $helper->log('Connector', $errorMessage);
            throw new Lengow_Connector_Model_Exception($timeout);
        }
        curl_close($ch);
        if ($result === false) {
            $errorCurl = $helper->setLogMessage(
                'lengow_log.exception.error_curl',
                array(
                    'error_code'    => $errorNumber,
                    'error_message' => $errorText
                )
            );
            $errorMessage = $helper->setLogMessage(
                'log.connector.error_api',
                array('error_code' => $helper->decodeLogMessage($errorCurl, 'en_GB'))
            );
            $helper->log('Connector', $errorMessage);
            throw new Lengow_Connector_Model_Exception($errorCurl);
        }
        return $result;
    }

    /**
     * Check API Authentification
     *
     * @param integer $accountId Account id
     *
     * @return boolean
     */
    public function isValidAuth($accountId)
    {
        if (is_null($accountId) || $accountId == 0 || !is_integer($accountId)) {
            return false;
        }
        try {
            $result = $this->connect();
        } catch (Lengow_Connector_Model_Exception $e) {
            return false;
        }
        if (isset($result['token'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get Valid Account / Access / Secret
     *
     * @param integer $storeId Store Id
     *
     * @return array
     */
    public function getAccessId($storeId = null)
    {
        $config = Mage::helper('lengow_connector/config');
        if ($storeId) {
            $accountId = (int)$config->get('account_id', $storeId);
            $accessToken = $config->get('access_token', $storeId);
            $secretToken = $config->get('secret_token', $storeId);
        } else {
            $storeCollection = Mage::getResourceModel('core/store_collection')->addFieldToFilter('is_active', 1);
            foreach ($storeCollection as $store) {
                $accountId = $config->get('account_id', $store->getId());
                $accessToken = $config->get('access_token', $store->getId());
                $secretToken = $config->get('secret_token', $store->getId());
                if (strlen($accountId) > 0 && strlen($accessToken) > 0 && strlen($secretToken) > 0) {
                    break;
                }
            }
        }
        if (strlen($accountId) > 0 && strlen($accessToken) > 0 && strlen($secretToken) > 0) {
            return array($accountId, $accessToken, $secretToken);
        } else {
            return array(null, null, null);
        }
    }

    /**
     * Get Connector by store
     *
     * @param integer $storeId Store Id
     *
     * @return boolean
     */
    public function getConnectorByStore($storeId = null)
    {
        list($accountId, $accessToken, $secretToken) = $this->getAccessId($storeId);
        $this->init($accessToken, $secretToken);
        if (!$this->isValidAuth($accountId)) {
            return false;
        }
        return true;
    }

    /**
     * Get result for a query Api
     *
     * @param string  $type    (GET / POST / PUT / PATCH)
     * @param string  $url
     * @param integer $storeId
     * @param array   $params
     * @param string  $body
     *
     * @return api result as array
     */
    public function queryApi($type, $url, $storeId = null, $params = array(), $body = '')
    {
        if (!in_array($type, array('get', 'post', 'put', 'patch'))) {
            return false;
        }
        try {
            list($accountId, $accessToken, $secretToken) = $this->getAccessId($storeId);
            $this->init($accessToken, $secretToken);
            if (!$this->isValidAuth($accountId)) {
                return false;
            }
            $results = $this->$type(
                $url,
                array_merge(array('account_id' => $accountId), $params),
                'stream',
                $body
            );
        } catch (Lengow_Connector_Model_Exception $e) {
            return false;
        }
        return json_decode($results);
    }
}
