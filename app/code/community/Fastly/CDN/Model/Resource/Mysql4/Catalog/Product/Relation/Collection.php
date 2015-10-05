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

class Fastly_CDN_Model_Resource_Mysql4_Catalog_Product_Relation_Collection
    extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     * Initialize resource model and define main table
     */
    protected function _construct()
    {
        $this->_init('fastlycdn/catalog_product_relation', 'catalog/product_relation');
    }

    /**
     * Filters collection by child product id
     *
     * @param int $childId
     * @return Fastly_CDN_Model_Resource_Mysql4_Catalog_Product_Relation_Collection
     */
    public function filterByChildId($childId)
    {
        $this->getSelect()
            ->where('child_id=?', $childId);
        return $this;
    }
}
