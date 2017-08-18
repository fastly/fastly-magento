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
     * Creates a new Dictionary.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function createDictionary($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/dictionary';
        $params = array('name' => $name);
        $verb = \Zend_Http_Client::POST;

        $result = $this->_fetch($url, $verb, $params);

        return $result;
    }

    /**
     * Remove Dictionary.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function deleteDictionary($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/dictionary/' . $name;
        $verb = \Zend_Http_Client::DELETE;

        $result = $this->_fetch($url, $verb);

        return $result;
    }

    /**
     * Gets the specified Dictionary object.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function getDictionary($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/dictionary/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Gets the all Dictionaries.
     *
     * @param $version
     * @return bool|mixed
     */
    public function getDictionaries($version)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/dictionary';
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Get All Dictionary Items.
     *
     * @param $id
     * @return bool|mixed
     */
    public function getDictionaryItems($id)
    {
        $url = $this->_getApiServiceUri(). 'dictionary/' . $id . '/items';
        $verb = \Zend_Http_Client::GET;

        $result = $this->_fetch($url, $verb);

        return $result;
    }

    /**
     * Get Single Dictionary Item.
     *
     * @param $id
     * @param $key
     * @return bool|mixed
     */
    public function getDictionaryItem($id, $key)
    {
        $url = $this->_getApiServiceUri(). 'dictionary/' . $id . '/item/' . $key;
        $verb = \Zend_Http_Client::GET;

        $result = $this->_fetch($url, $verb);

        return $result;
    }

    /**
     * Add Dictionary Item.
     *
     * @param $id
     * @param $params
     * @return bool|mixed
     */
    public function addDictionaryItem($id, $params)
    {
        $checkIfExists = $this->getDictionaryItem($id, $params['item_key']);
        $url = $this->_getApiServiceUri(). 'dictionary/' . $id . '/item';

        if(!$checkIfExists)
        {
            $verb = \Zend_Http_Client::POST;

            $params = array(
                'item_key' => $params['item_key'],
                'item_value' => $params['item_value']
            );

        } else {
            $verb = \Zend_Http_Client::PUT;
            $url .= '/'.$params['item_key'];

            $params = array(
                'item_value' => $params['item_value']
            );
        }

        $result = $this->_fetch($url, $verb, $params);

        return $result;
    }

    /**
     * Remove Dictionary Item.
     *
     * @param $id
     * @param $key
     * @return bool|mixed
     */
    public function removeDictionaryItem($id, $key)
    {
        $url = $this->_getApiServiceUri(). 'dictionary/' . $id . '/item/' . $key;
        $verb = \Zend_Http_Client::DELETE;

        $result = $this->_fetch($url, $verb);

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

    /**
     * Creates a new Acl.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function createAcl($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/acl';
        $params = array('name' => $name);
        $verb = \Zend_Http_Client::POST;

        $result = $this->_fetch($url, $verb, $params);

        return $result;
    }

    /**
     * Remove Acl.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function deleteAcl($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/' .$version. '/acl/' . $name;
        $verb = \Zend_Http_Client::DELETE;

        $result = $this->_fetch($url, $verb);

        return $result;
    }

    /**
     * Gets the specified Acl object.
     *
     * @param $version
     * @param $name
     * @return bool|mixed
     */
    public function getAcl($version, $name)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/acl/' . $name;
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Gets the all Acls.
     *
     * @param $version
     * @return bool|mixed
     */
    public function getAcls($version)
    {
        $url = $this->_getApiServiceUri(). 'version/'. $version. '/acl';
        $result = $this->_fetch($url, \Zend_Http_Client::GET);

        return $result;
    }

    /**
     * Get All Acl Items.
     *
     * @param $id
     * @return bool|mixed
     */
    public function getAclItems($id)
    {
        $url = $this->_getApiServiceUri(). 'acl/' . $id . '/entries';
        $verb = \Zend_Http_Client::GET;

        $result = $this->_fetch($url, $verb);

        return $result;
    }

    /**
     * Add Acl Item.
     *
     * @param $aclId
     * @param $itemValue
     * @param $aclItemId
     * @param $negated
     * @param $subnet
     * @return bool|mixed
     */
    public function addAclItem($aclId, $aclItemId, $itemValue, $negated, $subnet = false)
    {
        $checkIfExists = $aclItemId === 'false' ? false : $this->getAclItem($aclId, $aclItemId);
        $url = $this->_getApiServiceUri(). 'acl/' . $aclId . '/entry';

        $negated = $negated === 'true';

        if($subnet) {
            $params = array('ip' => $itemValue, 'negated' => $negated, 'comment' => 'Added by Magento Module', 'subnet' => $subnet);
        } else {
            $params = array('ip' => $itemValue, 'negated' => $negated, 'comment' => 'Added by Magento Module');
        }

        if(!$checkIfExists) {
            $verb = \Zend_Http_Client::POST;
        } else {
            $verb = \Zend_Http_Client::PATCH;
            $url .= '/'.$aclItemId;
        }

        $result = $this->_fetch($url, $verb, $params);

        return $result;
    }

    /**
     * Get Single Acl Item.
     *
     * @param $id
     * @param $entryId
     * @return bool|mixed
     */
    public function getAclItem($id, $entryId)
    {
        $url = $this->_getApiServiceUri(). 'acl/' . $id . '/entry/' . $entryId;
        $verb = \Zend_Http_Client::GET;

        $result = $this->_fetch($url, $verb);

        return $result;
    }

    /**
     * Remove Acl Item.
     *
     * @param $id
     * @param $entryId
     * @return bool|mixed
     */
    public function removeAclItem($id, $entryId)
    {
        $url = $this->_getApiServiceUri(). 'acl/' . $id . '/entry/' . $entryId;
        $verb = \Zend_Http_Client::DELETE;

        $result = $this->_fetch($url, $verb);

        return $result;
    }
}
