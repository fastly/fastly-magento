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

class Fastly_CDN_Model_Esi_Processor_GeoIp_Action extends Fastly_CDN_Model_Esi_Processor_Abstract
{
    const XML_PATH_GEOIP_SHOW_DIALOG    = 'fastlycdn/geoip/show_dialog';
    const XML_PATH_GEOIP_MAP_REDIRECT   = 'fastlycdn/geoip/map_redirect';
    const XML_PATH_GEOIP_MAP_CMS_BLOCKS = 'fastlycdn/geoip/map_cmsblock';


    /**
     * get html output
     *
     * @return string
     */
    public function getHtml()
    {
        $output = '';

        if ($this->_getHelper()->isGeoIpEnabled()) {
            $layout = Mage::app()->getLayout();
            if (Mage::getStoreConfigFlag(self::XML_PATH_GEOIP_SHOW_DIALOG)) {
                // show a dialog from CMS block
                if ($cmsBlockId = $this->_getMapping(self::XML_PATH_GEOIP_MAP_CMS_BLOCKS, 'cms')) {
                    $cmsBlock  = $layout->createBlock('cms/block')->setBlockId($cmsBlockId);
                    $output    = $layout->createBlock('core/template')
                                        ->setTemplate('fastlycdn/geoip/dialog.phtml')
                                        ->setChild('esiPopup', $cmsBlock)
                                        ->toHtml();
                }
            } else {
                // redirect to another store via JavaScript
                if ($redirectTo = $this->_getMapping(self::XML_PATH_GEOIP_MAP_REDIRECT, 'store')) {
                    $store = Mage::getModel('core/store')->load($redirectTo);
                    /* @var $store Mage_Core_Model_Store */
                    if ($store->getId()) {
                        $redirectUrl = $store->getUrl('', array('_nosid' => true));
                        $output = $layout->createBlock('core/template')
                            ->setTemplate('fastlycdn/geoip/redirect.phtml')
                            ->setRedirectUrl($redirectUrl)
                            ->toHtml();
                    }
                }
            }
        }

        return $output;
    }

    /**
     * get the mapping for a county code
     *
     * @param string $xmlPath   configuration path for mapping
     * @param string $key       column name of mapping value
     *
     * @return bool|string
     */
    protected function _getMapping($xmlPath, $key) {
        if ($countryCode = Mage::app()->getRequest()->getParam('country_code')) {
            if ($map = Mage::getStoreConfig($xmlPath)) {
                $map = unserialize($map);

                if (isset($map['country'])) {
                    // check for direct match
                    foreach ($map['country'] as $i => $country) {
                        if (strtolower($country) == strtolower($countryCode)) {
                            return $map[$key][$i];
                        }
                    }
                    // check for wildcard
                    foreach ($map['country'] as $i => $country) {
                        if ($country == '*') {
                            return $map[$key][$i];
                        }
                    }
                }
            }
        }
        return false;
    }
}