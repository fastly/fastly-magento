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

class Fastly_CDN_Helper_Cache extends Mage_Core_Helper_Abstract
{
    const XML_PATH_FASTLY_CDN_DISABLE_CACHING      = 'fastlycdn/general/disable_caching';
    const XML_PATH_FASTLY_CDN_DISABLE_CACHING_VARS = 'fastlycdn/general/disable_caching_vars';
    const XML_PATH_FASTLY_CDN_DISABLE_ROUTES       = 'fastlycdn/general/disable_routes';
    const XML_PATH_FASTLY_CDN_TTL                  = 'fastlycdn/general/ttl';
    const XML_PATH_FASTLY_CDN_STALE_TTL            = 'fastlycdn/general/stale_ttl';
    const XML_PATH_FASTLY_CDN_STALE_ERROR_TTL      = 'fastlycdn/general/stale_error_ttl';
    const XML_PATH_FASTLY_CDN_ROUTES_TTL           = 'fastlycdn/general/routes_ttl';

    const REGISTRY_VAR_FASTLY_CDN_CONTROL_HEADERS_SET_FLAG = 'fastly_cdn_control_headers_set_flag';

    /**
     * Header for debug flag
     *
     * @var string
     * @return void
     */
    const DEBUG_HEADER = 'X-Cache-Debug: 1';


    /**
     * Get Cookie object
     *
     * @return Mage_Core_Model_Cookie
     */
    public static function getCookie()
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Set appropriate cache control headers
     *
     * @return Fastly_CDN_Helper_Cache
     */
    public function setCacheControlHeaders()
    {
        if (Mage::registry(self::REGISTRY_VAR_FASTLY_CDN_CONTROL_HEADERS_SET_FLAG)) {
            return $this;
        } else {
            Mage::register(self::REGISTRY_VAR_FASTLY_CDN_CONTROL_HEADERS_SET_FLAG, 1);
        }

        // set debug header
        if (Mage::helper('fastlycdn')->isDebug()) {
            $this->setDebugHeader();
        }

        $request = Mage::app()->getRequest();

        // check for disable caching vars
        if ($disableCachingVars = trim(Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_DISABLE_CACHING_VARS))) {
            foreach (explode(',', $disableCachingVars) as $param) {
                if ($request->getParam(trim($param))) {
                    return $this->setNoCacheHeader();
                }
            }
        }

        /**
         * disable page caching
         */

        // disable page caching for POSTs
        if ($request->isPost()) {
            return $this->setNoCacheHeader();
        }

        // disable page caching for due to HTTP status codes
        if (!in_array(Mage::app()->getResponse()->getHttpResponseCode(), array(200, 301, 404))) {
            return $this->setNoCacheHeader();
        }

        // disable page caching for certain GET parameters
        $noCacheGetParams = array(
            'no_cache',     // explicit
            '___store'      // language switch
        );
        foreach($noCacheGetParams as $param) {
            if($request->getParam($param)) {
                return $this->setNoCacheHeader();
            }
        }

        // disable page caching because of configuration
        if (Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_DISABLE_CACHING)) {
            return $this->setNoCacheHeader();
        }


        /**
         * Check for ruleset depending on request path
         *
         * see: Mage_Core_Controller_Varien_Action::getFullActionName()
         */
        $fullActionName = $request->getRequestedRouteName().'_'.
            $request->getRequestedControllerName().'_'.
            $request->getRequestedActionName();

        // check caching blacklist for request routes
        $disableRoutes = explode("\n", trim(Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_DISABLE_ROUTES)));
        foreach ($disableRoutes as $route) {
            $route = trim($route);
            // if route is found at first position we have a hit
            if (!empty($route) && strpos($fullActionName, $route) === 0) {
                return $this->setNoCacheHeader();
            }
        }

        // set TTL header
        $regexp = null;
        $value = null;
        $routesTtl = unserialize(Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_ROUTES_TTL));
        if (is_array($routesTtl)) {
            foreach ($routesTtl as $routeTtl) {
                extract($routeTtl, EXTR_OVERWRITE);
                $regexp = trim($regexp);
                if (!empty($regexp) && strpos($fullActionName, $regexp) === 0) {
                    break;
                }
                $value = null;
            }
        }
        if (!isset($value)) {
            $value = Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_TTL);
        }
        $this->setTtlHeader(intval($value));

        return $this;
    }

    /**
     * Disable caching for this request
     *
     * @return Fastly_CDN_Helper_Cache
     */
    public static function setNoCacheHeader()
    {
        return self::setTtlHeader(0);
    }

    /**
     * Set debug flag in HTTP header
     *
     * @return Fastly_CDN_Helper_Cache
     */
    public function setDebugHeader()
    {
        $response = Mage::app()->getResponse();
        if (!$response->canSendHeaders()) {
            return;
        }
        $el = explode(':', self::DEBUG_HEADER, 2);
        $response->setHeader($el[0], $el[1], true);
        return $this;
    }

    /**
     * Set TTL HTTP header for cache
     *
     * For mod_expires it is important to have "Expires" header. However for
     * Fastly CDN it is easier to deal with "Cache-Control: s-maxage=xx" as it
     * is relative to its system time and not depending on timezone settings.
     *
     * Magento normaly doesn't set any Cache-Control or Expires headers. If they
     * appear the are set by PHP's setcookie() function.
     *
     * @param int   Time to life in seconds. Value greater than 0 means "cacheable".
     * @return void
     */
    public static function setTtlHeader($ttl)
    {
        // ttl of 0 means "PASS" but pass is done with "private"
        if ($ttl <= 0) {
            $maxAge = 'private';
        } else {
            $maxAge = 's-maxage=' . $ttl;
        }
        $cacheControlValue = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, '.$maxAge;

        // set stale timings
        $staleTime = (int)Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_STALE_TTL);
        if ($staleTime) {
            $cacheControlValue .= ', stale-while-revalidate=' . $staleTime;
        }

        $staleErrorTime = (int)Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_STALE_ERROR_TTL);
        if ($staleErrorTime) {
            $cacheControlValue  .= ', stale-if-error=' . $staleErrorTime;
        }

        // retrieve existing "Cache-Control" header
        $response = Mage::app()->getResponse();
        if (!$response->canSendHeaders()) {
            return;
        }
        $headers = $response->getHeaders();

        foreach ($headers as $key => $header) {
            if ('Cache-Control' == $header['name'] && !empty($header['value'])) {
                // replace existing "max-age" value
                if ((strpos($maxAge, 's-maxage') !== false) && (strpos($header['value'], 'age=') !== false)) {
                    $cacheControlValue = preg_replace('/(s-)?max[-]?age=[0-9]+/', $maxAge, $header['value']);
                } elseif (($maxAge === 'private') && (strpos($header['value'], 'private') !== false)) {
                    // do nothing
                } else {
                    $cacheControlValue = $header['value'] . ', ' . $maxAge;
                }
            }
        }

        // set "Cache-Control" header
        $response->setHeader('Cache-Control', $cacheControlValue, true);

        // set "Expires" header in the past to keep mod_expires from applying it's ruleset
        $response->setHeader('Expires', 'Mon, 31 Mar 2008 10:00:00 GMT', true);

        // set "Pragma: no-cache" - just in case
        $response->setHeader('Pragma', 'no-cache', true);
        $response->setHeader("Fastly-Module-Enabled", "1.0.14", true);
    }

    /**
     * Find all domains for store
     *
     * @param int    $storeId
     * @param string $seperator
     * @param array  $domains
     *
     * @return string
     */
    public function getStoreDomainList($storeId = 0, $seperator = '|', $domains = array())
    {
        if (empty($domains)) {
            $domains = $this->_getStoreDomainsArray($storeId);
        }

        return implode($seperator, $domains);
    }

    /**
     * @param int $storeId
     *
     * @return array
     */
    protected function _getStoreDomainsArray($storeId = 0)
    {
        if (!isset($this->_storeDomainArray[$storeId])) {
            $this->_storeDomainArray[$storeId] = array();

            $storeIds = array($storeId);
            // if $store is empty or 0 get all store ids
            if (empty($storeId)) {
                $storeIds = Mage::getResourceModel('core/store_collection')->getAllIds();
            }

            $urlTypes = array(
                Mage_Core_Model_Store::URL_TYPE_LINK,
                Mage_Core_Model_Store::URL_TYPE_DIRECT_LINK,
                Mage_Core_Model_Store::URL_TYPE_WEB,
                Mage_Core_Model_Store::URL_TYPE_SKIN,
                Mage_Core_Model_Store::URL_TYPE_JS,
                Mage_Core_Model_Store::URL_TYPE_MEDIA
            );
            foreach ($storeIds as $_storeId) {
                $store = Mage::getModel('core/store')->load($_storeId);

                foreach ($urlTypes as $urlType) {
                    // get non-secure store domain
                    $this->_storeDomainArray[$storeId][] = Zend_Uri::factory($store->getBaseUrl($urlType, false))->getHost();
                    // get secure store domain
                    $this->_storeDomainArray[$storeId][] = Zend_Uri::factory($store->getBaseUrl($urlType, true))->getHost();
                }
            }

            // get only unique values
            $this->_storeDomainArray[$storeId] = array_unique($this->_storeDomainArray[$storeId]);
        }

        return $this->_storeDomainArray[$storeId];
    }

    /**
     * Set appropriate cache control raw headers.
     * Called when script exits before controller_action_postdispatch
     * avoiding Zend_Controller_Response_Http#sendResponse()
     *
     * @return Fastly_CDN_Helper_Cache
     */
    public function setCacheControlHeadersRaw()
    {
        if (Mage::registry(self::REGISTRY_VAR_FASTLY_CDN_CONTROL_HEADERS_SET_FLAG)
            || Mage::app()->getStore()->isAdmin()) {
            return $this;
        }

        try {
            $response =  Mage::app()->getResponse();
            $response->canSendHeaders(true);
            $this->setCacheControlHeaders();
            foreach ($response->getHeaders() as $header) {
                header($header['name'] . ': ' . $header['value'], $header['replace']);
            }
        } catch (Exception $e) {
//            Mage::helper('fastlycdn')->debug(
//            	'Error while trying to set raw cache control headers: '.$e->getMessage()
//            );
        }

        return $this;
    }
}

