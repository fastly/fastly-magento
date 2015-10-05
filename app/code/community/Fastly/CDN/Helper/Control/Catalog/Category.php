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

class Fastly_CDN_Helper_Control_Catalog_Category extends Fastly_CDN_Helper_Data
{
    const XML_PATH_FASTLY_CDN_CACHE_PURGE  = 'fastlycdn/general/purge_catalog_category';

    /**
     * Returns true if Fastly CDN is enabled and category should be purged on save
     *
     * @return boolean
     */
    public function canPurge()
    {
        return $this->isEnabled() && $this->isPurge();
    }

    /**
     * Returns true if category should be purged on save
     *
     * @return boolean
     */
    public function isPurge()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_FASTLY_CDN_CACHE_PURGE);
    }
}
