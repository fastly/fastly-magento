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

class Fastly_CDN_Model_Control_Cms_Page extends Fastly_CDN_Model_Control_Abstract
{
    protected $_helperName = 'fastlycdn/control_cms_page';

    /**
     * Purge Cms Page
     *
     * @param Mage_Cms_Model_Page $page
     * @return Fastly_CDN_Model_Control_Cms_Page
     */
    public function purge(Mage_Cms_Model_Page $page)
    {
        if ($this->_canPurge()) {
            $surrogateKey = Fastly_CDN_Helper_Tags::SURROGATE_KEY_CMSPAGE_PREFIX . $page->getId();
            $this->_getCacheControl()->cleanBySurrogateKey($surrogateKey);

            $this->_getSession()->addSuccess(
            	Mage::helper('fastlycdn')->__('Fastly CDN for cms page "%s" has been purged.', $page->getTitle())
            );
        }

        return $this;
    }

}
