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

class Fastly_CDN_Model_Control_Catalog_Product extends Fastly_CDN_Model_Control_Abstract
{
    protected $_helperName = 'fastlycdn/control_catalog_product';

    /**
     * Purge product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param bool $purgeParentProducts
     * @param bool $purgeCategories
     * @return Fastly_CDN_Model_Control_Catalog_Product
     */
    public function purge(Mage_Catalog_Model_Product $product, $purgeParentProducts = false, $purgeCategories = false)
    {
        if ($this->_canPurge()) {
            $idsToPurge = array();
            $categoryIdsToPurge = array();
            $idsToPurge[] = $product->getId();
            $this->_getSession()->addSuccess(
            	Mage::helper('fastlycdn')->__('Fastly CDN for "%s" has been purged.', $product->getName())
            );

            if ($purgeParentProducts) {
                // purge parent products
                $productRelationCollection = $this->_getProductRelationCollection()
                    ->filterByChildId($product->getId());
                foreach ($productRelationCollection as $productRelation) {
                    $idsToPurge[] = $productRelation->getParentId();
                }
                // purge categories of parent products
                if ($purgeCategories) {
                    $categoryProductCollection = $this->_getCategoryProductRelationCollection()
                        ->filterAllByProductIds($productRelationCollection->getAllIds());

                    foreach ($categoryProductCollection as $categoryProduct) {
                        $categoryIdsToPurge[] = $categoryProduct->getCategoryId();
                    }
                }
            }

            $this->_purgeByIds($idsToPurge);

            if ($purgeCategories) {
                foreach ($product->getCategoryCollection() as $category) {
                    $categoryIdsToPurge[] = $category->getId();
                }
                $this->_getSession()->addSuccess(
                	Mage::helper('fastlycdn')->__('Fastly CDN for the product\'s categories has been purged.')
                );
            }

            $this->_purgeCategoriesByIds($categoryIdsToPurge);
        }
        return $this;
    }

    /**
     * Purge product by id
     *
     * @param int $id
     * @param bool $purgeParentProducts
     * @param bool $purgeCategories
     * @return Fastly_CDN_Model_Control_Catalog_Product
     */
    public function purgeById($id, $purgeParentProducts = false, $purgeCategories = false)
    {
        $product = Mage::getModel('catalog/product')->load($id);
        return $this->purge($product, $purgeParentProducts, $purgeCategories);
    }

    /**
     * Purge product by ids
     *
     * @param $ids
     *
     * @return Fastly_CDN_Model_Control_Catalog_Product
     */
    protected function _purgeCategoriesByIds($ids)
    {
        foreach ($ids as $id) {
            $surrogateKey = Fastly_CDN_Helper_Tags::SURROGATE_KEY_CATEGORY_PREFIX . $id;
            $this->_getCacheControl()->cleanBySurrogateKey($surrogateKey);
        }
    }

    /**
     * Purge product by ids
     *
     * @param $ids
     *
     * @return Fastly_CDN_Model_Control_Catalog_Product
     */
    protected function _purgeByIds($ids)
    {
        foreach ($ids as $id) {
            $surrogateKey = Fastly_CDN_Helper_Tags::SURROGATE_KEY_PRODUCT_PREFIX . $id;
            $this->_getCacheControl()->cleanBySurrogateKey($surrogateKey);
        }

        return $this;
    }
}
