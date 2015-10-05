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

class Fastly_CDN_Model_Control_Catalog_Category extends Fastly_CDN_Model_Control_Abstract
{
    protected $_helperName = 'fastlycdn/control_catalog_category';

    /**
     * Purge Category
     *
     * @param Mage_Catalog_Model_Category $category
     * @return Fastly_CDN_Model_Control_Catalog_Category
     */
    public function purge(Mage_Catalog_Model_Category $category)
    {
        if ($this->_canPurge()) {
            $this->_purgeById($category->getId());
            if ($categoryName = $category->getName()) {
                $this->_getSession()->addSuccess(
                	Mage::helper('fastlycdn')->__('Fastly CDN for "%s" has been purged.', $categoryName)
                );
            }
        }
        return $this;
    }

    /**
     * Purge Category by id
     *
     * @param int $id
     * @return Fastly_CDN_Model_Control_Catalog_Category
     */
    public function purgeById($id)
    {
        if ($this->_canPurge()) {
            $this->_purgeById($id);
        }
        return $this;
    }

    /**
     * Purge Category by id
     *
     * @param int $id
     * @return Fastly_CDN_Model_Control_Catalog_Category
     */
    protected function _purgeById($id)
    {
        $surrogateKey = Fastly_CDN_Helper_Tags::SURROGATE_KEY_CATEGORY_PREFIX . $id;
        $this->_getCacheControl()->cleanBySurrogateKey($surrogateKey);

        return $this;
    }
}
