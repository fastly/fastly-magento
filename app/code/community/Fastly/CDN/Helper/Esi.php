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

class Fastly_CDN_Helper_Esi extends Mage_Core_Helper_Abstract
{
    const ESI_FORMKEY_URL   = 'fastlycdn/getformkey/';
    const FORMKEY_COOKIE    = 'FASTLY_CDN_FORMKEY';
    const ESI_INCLUDE_OPEN  = '<esi:include src="';
    const ESI_INCLUDE_CLOSE = '" />';

    /**
     * return if used magento version uses form keys
     *
     * @return bool
     */
    public function hasFormKey()
    {
        $session = Mage::getSingleton('core/session');

        return method_exists($session, 'getFormKey');
    }

    /**
     * generate esi tag for form keys
     *
     * @return string
     */
    public function getFormKeyEsiTag()
    {
        $url = Mage::getUrl(
            self::ESI_FORMKEY_URL,
            array(
                '_nosid'  => true,
                '_secure' => false
            )
        );
        $esiTag = self::ESI_INCLUDE_OPEN . $url . self::ESI_INCLUDE_CLOSE;

        return $esiTag;
    }

    /**
     * Replace form key with esi tag
     *
     * @param string $content
     * @return string
     */
    public function replaceFormKey($content)
    {
        /** @var $session Mage_Core_Model_Session */
        $session = Mage::getSingleton('core/session');

        // replace all occurrences of form key with esi tag
        $content = str_replace(
            $session->getFormKey(),
            $this->getFormKeyEsiTag(),
            $content
        );

        return $content;
    }

    /**
     * Return the form key value stored in a cookie
     * or false if it is not set
     *
     * @return string|false
     */
    public function getCookieFormKey()
    {
        return Mage::getSingleton('core/cookie')->get(self::FORMKEY_COOKIE);
    }
}
