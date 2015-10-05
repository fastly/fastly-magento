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

class Fastly_CDN_Block_Html_Header extends Mage_Page_Block_Html_Header
{
    public function _construct()
    {
        parent::_construct();
    }

    /**
     * Returns the welcome message block
     *
     * @return mixed
     */
    public function getWelcome()
    {
        // check for esi enabled
        if (Mage::helper('fastlycdn')->canUseEsi()) {
            $esiTagModel = Mage::getModel(
                'fastlycdn/esi_tag_page_template_welcome',
                array(Mage::helper('fastlycdn')->getLayoutNameParam() => 'page/template_welcome')
            );

            $esiTag = $esiTagModel->getEsiIncludeTag($this);

            return $esiTag;
        }

        // no esi - use default behaviour
        return $this->getOriginalWelcome();
    }


    public function getOriginalWelcome()
    {
        return parent::getWelcome();
    }

}