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
 * System cache management additional block
 *
 * @category    Fastly
 * @package     Fastly_CDN
 */
class Fastly_CDN_Block_Adminhtml_Cache_Additional extends Mage_Adminhtml_Block_Template
{
    /**
     * Get clean cache url
     *
     * @return string
     */
    public function getCleanFastlyCDNUrl()
    {
        return $this->getUrl('*/fastlyCdn/cleanStore');
    }

    /**
     * Get clean cache url
     *
     * @return string
     */
    public function getCleanContentTypeUrl()
    {
        return $this->getUrl('*/fastlyCdn/cleanContentType');
    }

    /**
     * Returns Quick Purge URL
     *
     * @return string
     */
    public function getQuickPurgeUrl()
    {
        return $this->getUrl('*/fastlyCdn/quickPurge');
    }

    /**
     * Check if block can be displayed
     *
     * @return bool
     */
    public function canShowButton()
    {
        return Mage::helper('fastlycdn')->isEnabled();
    }

    /**
     * Get store selection
     *
     * @return string
     */
    public function getStoreOptions()
    {
        $stores = Mage::getModel('adminhtml/system_config_source_store')->toOptionArray();
        return $stores;
    }

    /**
     * Get content types
     */
    public function getContentTypeOptions()
    {
        foreach (Mage::getModel('fastlycdn/control')->getContentTypes() as $value => $label) {
            $options[] = array('value' => $value, 'label' => $label);
        }
        return $options;
    }
}
