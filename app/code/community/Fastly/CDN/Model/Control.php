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
}
