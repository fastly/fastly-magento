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
class Fastly_CDN_Model_Resource_Mysql4_Core_Url_Rewrite_Collection extends Mage_Core_Model_Mysql4_Url_Rewrite_Collection
{
    /**
     * Filter collection by category id
     *
     * @param int $categoryId
     * @return Fastly_CDN_Model_Resource_Mysql4_Core_Url_Rewrite_Collection
     */
    public function filterAllByCategoryId($categoryId)
    {
        $this->getSelect()
            ->where('id_path = ?', "category/{$categoryId}");
        return $this;
    }
}
