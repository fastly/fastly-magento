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

class Fastly_CDN_Model_Control_Abstract
{
    protected $_helperName;

    /**
     * Retrieve adminhtml session model object
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Returns true if Fastly CDN is enabled and Purge Product config option set to 1
     *
     * @return bool
     */
    protected function _canPurge()
    {
        if (!$this->_helperName) {
            return false;
        }
        return Mage::helper($this->_helperName)->canPurge();
    }

    /**
     * Get fastlyCDN control model
     *
     * @return Fastly_CDN_Model_Control
     */
    protected function _getCacheControl()
    {
        return Mage::helper('fastlycdn')->getCacheControl();
    }

    /**
     * Get url rewrite collection
     *
     * @return Fastly_CDN_Model_Resource_Mysql4_Core_Url_Rewrite_Collection
     */
    protected function _getUrlRewriteCollection()
    {
        return Mage::getResourceModel('fastlycdn/core_url_rewrite_collection');
    }

    /**
     * Get product relation collection
     *
     * @return Fastly_CDN_Model_Resource_Mysql4_Catalog_Product_Relation_Collection
     */
    protected function _getProductRelationCollection()
    {
        return Mage::getResourceModel('fastlycdn/catalog_product_relation_collection');
    }

    /**
     * Get catalog category product relation collection
     *
     * @return Fastly_CDN_Model_Resource_Mysql4_Catalog_Product_Relation_Collection
     */
    protected function _getCategoryProductRelationCollection()
    {
        return Mage::getResourceModel('fastlycdn/catalog_category_product_collection');
    }
}
