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

class Fastly_CDN_Block_Esi_Catalog_Product extends Mage_Core_Block_Template
{
    /**
     * Get ID of currently viewed product
     * @return int
     */
    public function getProductId()
    {
        $product = Mage::registry('current_product');
        if ($product) {
            return $product->getId();
        }
        return false;
    }

    /**
     * Get maximum count of products allowed in the list
     * @return int
     */
    public function getLimit()
    {
        return Mage::getStoreConfig(Mage_Reports_Block_Product_Viewed::XML_PATH_RECENTLY_VIEWED_COUNT);
    }

    /**
     * Get cookie name for viewed product ID list
     * @return string
     */
    public function getCookieName()
    {
        return Mage::helper('fastlycdn')->generateCookieName(
            Fastly_CDN_Model_Esi_Tag_Reports_Product_Viewed::COOKIE_NAME
        );
    }
}
