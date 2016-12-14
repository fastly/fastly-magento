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

class Fastly_CDN_Model_Esi_Tag_Abstract extends Mage_Core_Model_Abstract
{
    const ESI_REMOVE_START_TAG = '<esi:remove>';
    const ESI_REMOVE_END_TAG   = '</esi:remove>';
    const COOKIE_NAME          = '';
    const ESI_URL              = '';

    /**
     * Return ESI include tag with params for this block.
     *
     * @param Mage_Core_Block_Abstract
     * @return string
     */
    public function getEsiIncludeTag(Mage_Core_Block_Abstract $block)
    {
        $esiTag = '';

        if (($esiUrl = $this->_getEsiUrl()) && ($cookieName = $this->_getEsiCookieName())) {

            // check if current product exists in registry and obtain current product id
            $currentProductId = null;

            if(Mage::registry('current_product')) {
                $currentProductId = Mage::registry('current_product')->getId();
            }

            // set ESI parameters to determine block properties in URL
            $query = array(
                $this->_getHelper()->getLayoutNameParam()     => $this->getLayoutName(),
                $this->_getHelper()->getEsiDataParam()        => $cookieName,
                $this->_getHelper()->getLayoutHandlesParam()  => $this->_getLayoutHandles($block),
                $this->_getHelper()->getIsSecureParam()       => Mage::app()->getStore()->isCurrentlySecure() ? '1' : '0',
                $this->_getHelper()->getCurrentProductIdParam() => $currentProductId
            );

            // add additional (block specific) query params
            if ($additionalQueryParams = $this->_getAdditionalQueryParams()) {
                $query = array_merge($query, $additionalQueryParams);
            }

            $url = Mage::getUrl($esiUrl, array('_nosid' => true, '_query' => $query));

            // make url relative
            $url = str_replace(Mage::getBaseUrl(), '/', $url);

            $esiTag = '<esi:include src="' . $url . '" />';

            // add plain ESI include URL for debugging
            if ($this->_getHelper()->isEsiDebugEnabled()) {
                $esiTag .= '<em style="color:red">' . $url . '</em>';
            }
        }

        return $esiTag;
    }

    /**
     * @return string
     */
    public function getStartRemoveTag()
    {
        return self::ESI_REMOVE_START_TAG;
    }

    /**
     * @return string
     */
    public function getEndRemoveTag()
    {
        return self::ESI_REMOVE_END_TAG;
    }

    /**
     * @return string
     */
    protected function _getEsiUrl()
    {
        $c = get_class($this);
        eval('$val = '.$c.'::ESI_URL;');
        return $val;
    }

    /**
     * @return string
     */
    protected function _getEsiCookieName()
    {
        $c = get_class($this);
        eval('$val = '.$c.'::COOKIE_NAME;');
        return $val;
    }

    /**
     * Get active layout handles
     *
     * @param Mage_Core_Block_Abstract
     * @return array
     */
    protected function _getLayoutHandles(Mage_Core_Block_Abstract $block)
    {
        // get all layout handles of current page
        $handles = $block->getLayout()->getUpdate()->getHandles();

        /**
         * skip these layout handles as they are causing to many unnecessary ESI block variants when added to the URL.
         */
        $skipHandles = array('CATEGORY_', 'PRODUCT_', 'STORE_', 'customer_', 'THEME_', 'SHORTCUT_', 'page_','cms_', 'catalogsearch_');
        foreach ($handles as $key => $val) {
            // remove category handle
            foreach ($skipHandles as $skipHandle) {
                if (strpos($val, $skipHandle) === 0) {
                    unset($handles[$key]);
                }
            }
        }

        return implode(',', $handles);
    }

    /**
     * @return Fastly_CDN_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('fastlycdn');
    }

    /**
     * @return null|array
     */
    protected function _getAdditionalQueryParams()
    {
        return null;
    }
}
