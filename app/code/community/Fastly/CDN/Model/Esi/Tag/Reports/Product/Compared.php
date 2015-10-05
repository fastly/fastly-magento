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

class Fastly_CDN_Model_Esi_Tag_Reports_Product_Compared extends Fastly_CDN_Model_Esi_Tag_Abstract
{
    const COOKIE_NAME    = 'reports_product_compared';
    const ESI_URL        = 'fastlycdn/esi/reports_product_compared';

    /**
     * Report blocks exclude the current product ID from their list on product detail pages. Create a separate ESI block
     * per product page by adding the current product ID to the URL if strict rendering is enabled.
     *
     * @return null|array
     */
    protected function _getAdditionalQueryParams()
    {
        if ($this->_getHelper()->isStrictRenderingEnabled()) {
            if (($product = Mage::registry('current_product')) && ($product instanceof Mage_Catalog_Model_Product)) {
                return array('product_id' => $product->getId());
            }
        }
        return parent::_getAdditionalQueryParams();
    }
}