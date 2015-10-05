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

class Fastly_CDN_Model_Esi_Processor_Abstract extends Mage_Core_Model_Abstract
{
    const XML_PATH_FASTLY_CDN_ESI_BLOCK_TTL   = 'fastlycdn/esi/blocks_ttl';
    const XML_PATH_FASTLY_CDN_ESI_DEFAULT_TTL = 'fastlycdn/esi/default_ttl';

    protected $_block = null;

    /**
     * Load and init block
     */
    protected function _construct()
    {
        if ($layoutName = $this->getLayoutName()) {
            $block = Mage::app()->getLayout()->getBlock($layoutName);
            $this->_initBlock($block);
            $this->_block = $block;
        }
    }

    /**
     * Initializes block environment.
     *
     * @param mixed $block
     * @return void
     */
    protected function _initBlock($block)
    {
        return $this;
    }

    /**
     * Return block HTML
     *
     * @see Mage_Core_Block_Abstract::toHtml
     * @return string
     */
    public function getHtml()
    {
        $content = '';
        if ($this->_block instanceof Mage_Core_Block_Abstract) {
            $debug = $this->_getHelper()->isEsiDebugEnabled();
            if ($debug) {
                $content .= '<div style="border: 2px dotted red">';
            }

            $content .= $this->_block->toHtml();

            if ($debug) {
                $content .= '</div>';
            }
        }
        return $content;
    }

    /**
     * @return Fastly_CDN_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('fastlycdn');
    }

    /**
     * Return TTL for given block.
     *
     * @param $block string
     * @return int
     */
    public function getEsiBlockTtl($block)
    {
        $ttl = null;

        // get block specific ttl
        $esiBlockTtls = unserialize(Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_ESI_BLOCK_TTL));
        if (is_array($esiBlockTtls)) {
            foreach ($esiBlockTtls as $esiBlock) {
                if ($esiBlock['regexp'] == $block) {
                    $ttl = $esiBlock['value'];
                }
            }
        }

        // ttl not set for block - use default
        if (is_null($ttl)) {
            $ttl = Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_ESI_DEFAULT_TTL);
        }

        return (int)$ttl;
    }
}
