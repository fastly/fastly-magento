<?php
/**
 * Fastly CDN for Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Fastly CDN for Magento End User License Agreement
 * that is bundled with this package in the file LICENSE_FASTLY_CDN.txt.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Fastly CDN to newer
 * versions in the future. If you wish to customize this module for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Fastly
 * @package     Fastly_CDN
 * @copyright   Copyright (c) 2015 Fastly, Inc. (http://www.fastly.com)
 * @license     BSD, see LICENSE_FASTLY_CDN.txt
 */

/**
 * Fastly CDN control model
 *
 * @category    Fastly
 * @package     Fastly_CDN
 */
class Fastly_CDN_Model_Control
{
    const CONTENT_TYPE_HTML    = 'text';
    const CONTENT_TYPE_CSS     = 'css';
    const CONTENT_TYPE_JS      = 'script';
    const CONTENT_TYPE_IMAGE   = 'image';
    const FASTLY_HEADER_AUTH   = 'Fastly-Key';
    const FASTLY_HEADER_TOKEN  = 'X-Purge-Token';
    const FASTLY_API_ENDPOINT  = 'https://api.fastly.com/';
    const PURGE_TIMEOUT        = 15;
    const PURGE_TOKEN_LIFETIME = 30;

    /**
     * Get content types as option array
     */
    public function getContentTypes()
    {
        $contentTypes = array(
            self::CONTENT_TYPE_HTML  => Mage::helper('fastlycdn')->__('HTML'),
            self::CONTENT_TYPE_CSS   => Mage::helper('fastlycdn')->__('CSS'),
            self::CONTENT_TYPE_JS    => Mage::helper('fastlycdn')->__('JavaScript'),
            self::CONTENT_TYPE_IMAGE => Mage::helper('fastlycdn')->__('Images')
        );

        return $contentTypes;
    }

    /**
     * Purge a single URL
     *
     * @param $url
     */
    public function cleanUrl($url)
    {
        $this->_purge($url, 'PURGE');
    }

    /**
     * Purge fastly by a given surrogate key
     *
     * @param string $key
     * @return bool
     */
    public function cleanBySurrogateKey($key)
    {
        try {
            $uri = $this->_getApiServiceUri()
                . 'purge/'
                . $key;

            $this->_purge($uri);
            Mage::helper('fastlycdn')->debug('Purged fastlyCDN items with surrogate key ' . var_export($key, true));
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Error during purging: ' . $e->getMessage());
            return false;
        }

        return true;
    }


    /**
     * Purge all of fastly's CDN content
     *
     * @return bool
     */
    public function cleanAll()
    {
        try {
            $uri = $this->_getApiServiceUri()
                . 'purge_all';

            $this->_purge($uri);
            Mage::helper('fastlycdn')->debug('Purged all fastlyCDN items');
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Error during purging: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Test Connection with Fastly service
     *
     * @param $serviceId
     * @param $apiKey
     * @return bool|Zend_Http_Response
     */
    public function testConnection($serviceId, $apiKey)
    {
        $uri = self::FASTLY_API_ENDPOINT . 'service/' . $serviceId;
        $result = $this->_fetch($uri, 'GET', null, true, $apiKey);

        return $result;
    }

    /**
     * List detailed information on a specified service
     *
     * @param bool $test
     * @param $serviceId
     * @param $apiKey
     * @return bool|mixed
     */
    public function checkServiceDetails($test = false, $serviceId = null, $apiKey = null)
    {
        if(!$test) {
            $uri = rtrim($this->_getApiServiceUri(), '/');
            $result = $this->_fetch($uri);
        } else {
            $uri = self::FASTLY_API_ENDPOINT . 'service/' . $serviceId;
            $result = $this->_fetch($uri, \Zend_Http_Client::GET, null, true, $apiKey);
        }

        return $result;
    }

    /**
     * Activate the current version.
     *
     * @param $version
     * @return bool|mixed
     */
    public function activateVersion($version)
    {
        $url = $this->_getApiServiceUri() . 'version/' .$version. '/activate';
        $result = $this->_fetch($url, 'PUT');

        return $result;
    }

    /**
     * Clone the current configuration into a new version.
     *
     * @param $curVersion
     * @return bool|mixed
     */
    public function cloneVersion($curVersion)
    {
        $url = $this->_getApiServiceUri() . 'version/'.$curVersion.'/clone';
        $result = $this->_fetch($url, \Zend_Http_Client::PUT);

        return $result;
    }

    /**
     * Validate the version for a particular service and version.
     *
     * @param $version
     * @return bool|mixed
     */
    public function validateServiceVersion($version)
    {
        $url = $this->_getApiServiceUri() . 'version/' .$version. '/validate';
        $result = $this->_fetch($url, 'GET');

        return $result;
    }

    /**
     * Creating and updating a regular VCL Snippet
     *
     * @param $version
     * @param array $snippet
     * @return bool|mixed*
     */
    public function uploadSnippet($version, array $snippet)
    {
        $checkIfExists = $this->getSnippet($version, $snippet['name']);
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/snippet';
        if(!$checkIfExists)
        {
            $verb = \Zend_Http_Client::POST;
        } else {
            $verb = \Zend_Http_Client::PUT;
            $url .= '/'.$snippet['name'];
            unset($snippet['name'], $snippet['type'], $snippet['dynamic'], $snippet['priority']);
        }

        $result = $this->_fetch($url, $verb, $snippet);

        return $result;
    }

    /**
     * Fetching an individual regular VCL Snippet
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function getSnippet($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/snippet/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Creates a new condition
     *
     * @param $version
     * @param $condition
     * @return bool|mixed
     */
    public function createCondition($version, array $condition)
    {
        $checkIfExists = $this->getCondition($version, $condition['name']);
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/condition';
        if(!$checkIfExists)
        {
            $verb = \Zend_Http_Client::POST;
        } else {
            $verb = \Zend_Http_Client::PUT;
            $url .= '/'.$condition['name'];
        }

        $result = $this->_fetch($url, $verb, $condition);

        return $result;
    }

    /**
     * Gets the specified condition.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function getCondition($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/condition/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Creates a new Response Object.
     *
     * @param $version
     * @param array $response
     * @return bool $result
     */
    public function createResponse($version, array $response)
    {
        $checkIfExists = $this->getResponse($version, $response['name']);
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/response_object';
        if(!$checkIfExists)
        {
            $verb = \Zend_Http_Client::POST;
        } else {
            $verb = \Zend_Http_Client::PUT;
            $url .= '/'.$response['name'];
        }

        $result = $this->_fetch($url, $verb, $response);

        return $result;
    }

    /**
     * Gets the specified Response Object.
     *
     * @param string $version
     * @param string $name
     * @return bool|mixed $result
     */
    public function getResponse($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/response_object/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Creates a new Request Settings object.
     *
     * @param $version
     * @param $request
     * @return bool|mixed
     */
    public function createRequest($version, $request)
    {
        $checkIfExists = $this->getRequest($version, $request['name']);
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/request_settings';
        if(!$checkIfExists)
        {
            $verb = \Zend_Http_Client::POST;
        } else {
            $verb = \Zend_Http_Client::PUT;
            $url .= '/'.$request['name'];
        }

        $result = $this->_fetch($url, $verb, $request);

        return $result;
    }

    /**
     * Gets the specified Request Settings object.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function getRequest($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/request_settings/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Removes the specified Request Settings object.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function deleteRequest($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/request_settings/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::DELETE);

        return $result;
    }

    /**
     * List all backends for a particular service and version.
     *
     * @param $version
     * @return bool|mixed
     */
    public function getBackends($version)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/backend';
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    public function configureBackend($params, $version, $old_name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version . '/backend/' . str_replace ( ' ', '%20', $old_name);
        $result = $this->_fetch($url, \Zend_Http_Client::PUT, $params);

        return $result;
    }

    /**
     * Returns the fastly api service uri
     *
     * @return string
     */
    protected function _getApiServiceUri()
    {
        $uri = self::FASTLY_API_ENDPOINT
            . 'service/'
            . Mage::helper('fastlycdn')->getServiceId()
            . '/';

        return $uri;
    }

    /**
     * purge fastly
     *
     * @param string $uri
     */
    protected function _purge($uri, $verb = 'POST')
    {
        // create purge token
        $expiration   = time() + self::PURGE_TOKEN_LIFETIME;
        $stringToSign = parse_url($uri, PHP_URL_PATH) . $expiration;
        $signature    = hash_hmac('sha1', $stringToSign, Mage::helper('fastlycdn')->getServiceId());
        $token        = $expiration . '_' . urlencode($signature);

        // set headers
        $headers = array(
            self::FASTLY_HEADER_AUTH  => Mage::helper('fastlycdn')->getApiKey(),
            self::FASTLY_HEADER_TOKEN => $token
        );

        // soft purge if needed
        if (Mage::helper('fastlycdn')->useSoftPurge()) {
            $headers['Fastly-Soft-Purge'] = 1;
        }

        try {
            // create HTTP client
            $client = new Zend_Http_Client();
            $client->setUri($uri)
                ->setHeaders($headers)
                ->setConfig(array('timeout' => self::PURGE_TIMEOUT));

            // send POST request
            $response = $client->request($verb);

            // check response
            if ($response->getStatus() != '200') {
                throw new Exception('Return status ' . $response->getStatus());
            }
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Purging failed (' . $e->getMessage() . ').');
        }
    }

    /**
     * Fetch Fastly
     * Fetch Fastly API
     *
     * @param $uri
     * @param string $verb
     * @param string $body
     * @param bool $test
     * @param null $testApiKey
     * @return bool|Zend_Http_Response
     */
    protected function _fetch($uri, $verb = 'GET', $body = '', $test = false, $testApiKey = null)
    {
        // set headers

        if($test) {
            $apiKey = $testApiKey;
        } else {
            $apiKey = Mage::helper('fastlycdn')->getApiKey();
        }

        $headers = array(
            self::FASTLY_HEADER_AUTH  => $apiKey,
        );

        if($verb == \Zend_Http_Client::PUT) {
            array_push($headers, 'Content-Type: application/x-www-form-urlencoded');
        }

        try {
            // create HTTP client
            $adapter = new Zend_Http_Client_Adapter_Curl();
            $client = new Zend_Http_Client();
            $client->setAdapter($adapter);

            if($verb == \Zend_Http_Client::PUT) {
                $adapter->setConfig(array('curloptions' =>
                    array(
                        CURLOPT_CUSTOMREQUEST => 'PUT',
                        CURLOPT_POSTFIELDS => http_build_query($body)
                    )));
            } elseif($verb == \Zend_Http_Client::DELETE) {
                $adapter->setConfig(array('curloptions' =>
                    array(
                        CURLOPT_CUSTOMREQUEST => 'DELETE',
                    )));
            }

            $client->setUri($uri)->setHeaders($headers);
            if($body != '') {
                $client->setParameterPost($body);
            }
            // send request
            $response = $client->request($verb);

            // check response
            if ($response->getStatus() != '200') {
                throw new Exception('Return status ' . $response->getStatus());
            }

            return json_decode($response->getBody());
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Fetching failed (' . $e->getMessage() . ').');
            return false;
        }
    }
}
