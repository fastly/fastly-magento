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

class Fastly_CDN_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_FASTLY_CDN_SERVICE_ID                    = 'fastlycdn/general/service_id';
    const XML_PATH_FASTLY_CDN_API_KEY                       = 'fastlycdn/general/api_key';
    const XML_PATH_FASTLY_CDN_ENABLED                       = 'fastlycdn/general/enabled';
    const XML_PATH_FASTLY_CDN_DEBUG                         = 'fastlycdn/general/debug';
    const XML_PATH_FASTLY_CDN_USE_SOFT_PURGE                = 'fastlycdn/general/soft_purge';
    const XML_PATH_FASTLY_CDN_ESI_ENABLED                   = 'fastlycdn/esi/enabled';
    const XML_PATH_FASTLY_CDN_ESI_TAG_CONFIG                = 'fastlycdn_esi_tags/';
    const XML_PATH_FASTLY_CDN_ESI_STRICT_RENDERING_ENABLED  = 'fastlycdn/esi/strict_rendering_enabled';
    const XML_PATH_FASTLY_CDN_ESI_DEBUG_ENABLED             = 'fastlycdn/esi/debug';
    const XML_PATH_FASTLY_CDN_GEOIP_ENABLED                 = 'fastlycdn/geoip/enabled';

    const PARAM_LAYOUT_NAME    = 'layout_name';
    const PARAM_LAYOUT_HANDLES = 'layout_handles';
    const PARAM_ESI_DATA       = 'esi_data';
    const PARAM_IS_SECURE      = 'is_secure';
    const PARAM_CURRENT_PRODUCT_ID = 'current_product_id';

    const FASTLY_LOG_FILENAME  = 'fastlyCDN.log';

    const COOKIE_GEOIP_PROCESSED = 'FASTLY_CDN_GEOIP_PROCESSED';


    /**
     * Check whether Fastly CDN is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_ENABLED);
    }

    /**
     * Check whether debuging is enabled
     *
     * @return bool
     */
    public function isDebug()
    {
        if (Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_DEBUG)) {
            return true;
        }
        return false;
    }

    /**
     * Check if to use soft purge
     *
     * @return bool
     */
    public function useSoftPurge()
    {
        if (Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_USE_SOFT_PURGE)) {
            return true;
        }
        return false;
    }

    /**
     * Log debugging data
     *
     * @param string|array
     * @return void
     */
    public function debug($debugData)
    {
        if ($this->isDebug()) {
            Mage::log($debugData, null, self::FASTLY_LOG_FILENAME);
        }
    }

    /**
     * Get control model
     *
     * @return Fastly_CDN_Model_Control
     */
    public function getCacheControl()
    {
        return Mage::getSingleton('fastlycdn/control');
    }

    /**
     * Returns if "no cache" params are in GET request
     *
     * @return boolean
     */
    protected function _isNoCacheParamsInRequest()
    {
        $request = Mage::app()->getRequest();

        // disable ESI for certain GET parameters
        $noCacheGetParams = array(
            'no_cache',     // explicit
            '___store',     // language switch
            '___from_store' // language switch
        );

        foreach ($noCacheGetParams as $param) {
            if ($request->getParam($param)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns if request is of type POST
     *
     * @return bool
     */
    public function isPostRequest()
    {
        $request = Mage::app()->getRequest();
        if($request->isPost()) {
            return true;
        }

        return false;
    }

    /**
     * Returns if ESI is enabled in config
     *
     * @return bool
     */
    public function isEsiEnabled()
    {
        if (Mage::helper('fastlycdn')->isEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * Returns if ESI can be applied and will
     * be processed in vlc
     *
     * @return bool
     */
    public function canUseEsi()
    {
        if ($this->isEsiEnabled()) {
            // no ESI for POST requests
            if ($this->isPostRequest()) {
                return false;
            }

            return true;
        }

        // esi is disabled
        return false;
    }


    /**
     * Returns if strict rendering is enabled
     *
     * @return bool
     */
    public function isStrictRenderingEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_ESI_STRICT_RENDERING_ENABLED);
    }

    /**
     * Returns if ESI debug is enabled
     *
     * @return bool
     */
    public function isEsiDebugEnabled()
    {
        return $this->canUseEsi() && Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_ESI_DEBUG_ENABLED);
    }

    /**
     * Returns if GeoIP is enabled
     *
     * @return bool
     */
    public function isGeoIpEnabled()
    {
        if ($this->canUseEsi() && Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_GEOIP_ENABLED)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the action from a request string
     *
     * @return string
     */
    public function getActionFromRequest()
    {
        $pathParts = explode('/', Mage::app()->getRequest()->getPathInfo());

        if (empty($pathParts[3]) === false) {
            return $pathParts[3];
        }

        return false;
    }

    /**
     * Returns the cookie name for a tag
     *
     * @param string $var
     * @return string
     */
    public function generateCookieName($var)
    {
        return 'FASTLY_CDN-' . $var;
    }

    /**
     * Returns the processor associated with an action
     *
     * @param string action
     * @return string
     */
    public function getProcessorByAction($action)
    {
        $config = Mage::getConfig()->getNode(self::XML_PATH_FASTLY_CDN_ESI_TAG_CONFIG . $action);

        if (empty($config) === false) {
            if (isset($config->processor)) {
                return $config->processor;
            }
        }

        return false;
    }

    /**
     * Returns the fastly api key
     *
     * @return string
     */
    public function getApiKey()
    {
        return trim(Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_API_KEY));
    }

    /**
     * Returns the fastly service id
     *
     * @return string
     */
    public function getServiceId()
    {
        return trim(Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_SERVICE_ID));
    }

    /**
     * Get ESI Tag model by block instance
     *
     * @param Mage_Core_Block_Abstract $block
     * @return Fastly_CDN_Model_Esi_Tag_Abstract | bool
     */
    public function getEsiTagModelByBlock(Mage_Core_Block_Abstract $block)
    {
        $type = $block->getType();
        if ($esiTagClass = Mage::getSingleton('fastlycdn/config')->getEsiTagClassByBlockType($type)) {
            try {
                $model = Mage::getModel(
                    $esiTagClass,
                    array($this->getLayoutNameParam() => $block->getNameInLayout())
                );

                if ($model instanceof Fastly_CDN_Model_Esi_Tag_Abstract) {
                    return $model;
                }

                Mage::throwException($esiTagClass . ' is not a valid ESI tag class');
            } catch (Exception $e) {
                Mage::helper('fastlycdn')->debug($e->getMessage());
            }
        }
        return false;
    }

    /**
     * Returns a obscured value from an array
     *
     * @see Mage_Core_Helper_Data::encrypt
     * @param array data
     * @return string
     */
    public function getObscuredValue($data)
    {
        if (is_array($data)) {
            $data = serialize($data);
        }

        return Mage::helper('core')->encrypt($data);
    }

    /**
     * @return string
     */
    public function getLayoutNameParam()
    {
        return self::PARAM_LAYOUT_NAME;
    }

    /**
     * @return string
     */
    public function getEsiDataParam()
    {
        return self::PARAM_ESI_DATA;
    }

    /**
     * @return string
     */
    public function getLayoutHandlesParam()
    {
        return self::PARAM_LAYOUT_HANDLES;
    }

    /**
     * @return string
     */
    public function getIsSecureParam()
    {
        return self::PARAM_IS_SECURE;
    }

    public function getCurrentProductIdParam()
    {
        return self::PARAM_CURRENT_PRODUCT_ID;
    }

    /**
     * @return string
     */
    public function getGeoIpCookieName()
    {
        return self::COOKIE_GEOIP_PROCESSED;
    }

    /**
     * get esi data var from get params
     *
     * @return string
     */
    public function getEsiParam()
    {
        $param = Mage::app()->getRequest()->getParam(self::PARAM_ESI_DATA);
        if (empty($param) === false) {
            return json_decode($param);
        }

        return false;
    }

    /**
     * sets a ESI specific cookie
     *
     * @param array data
     * @param boolean setTimeStamp
     * @param string cookieName
     * @return void
     */
    public function setEsiCookie($data, $setTimeStamp = false, $cookieName)
    {
        $cookieData = array(
            'esiHash' => $this->getObscuredValue($data)
        );

        if ($setTimeStamp) {
            $cookieData['time'] = time();
        }

        Mage::getSingleton('core/cookie')->set(
            $this->generateCookieName($cookieName),
            json_encode($cookieData)
        );
    }

    /**
     * gets a variable from an ESI specific cookie
     *
     * @param string cookieName
     * @param string dataName
     * @return mixed
     */
    public function getEsiCookieData($cookieName, $dataName)
    {
        $cookie = Mage::getSingleton('core/cookie')->get(
            $this->generateCookieName($cookieName)
        );

        if (empty($cookie) !== false) {
            $jsonData = json_decode($cookie);

            if (isset($jsonData->$dataName)) {
                return $jsonData->$dataName;
            }
        }

        return false;
    }
}
