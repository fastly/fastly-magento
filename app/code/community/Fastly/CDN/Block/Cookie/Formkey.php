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

class Fastly_CDN_Block_Cookie_Formkey extends Mage_Core_Block_Template
{
    protected function _construct()
    {
        // set default cache lifetime and cache tags
        $this->addData(array(
            'cache_lifetime'    => null
       ));
    }


    /**
     * Return environment cookie name
     *
     * @return string
     */
    public function getCookieName()
    {
        return Fastly_CDN_Helper_Esi::FORMKEY_COOKIE;
    }

    /**
     * Return the form key esi tag
     *
     * @return string
     */
    public function getFormKeyValue()
    {
        // try to use form key from session
        $session = Mage::getSingleton('core/session');
        $formKey = $session->getData('_form_key');

        // or create new one via esi
        if (empty($formKey)) {
            $formKey = Mage::helper('fastlycdn/esi')->getFormKeyEsiTag();
        }

        return $formKey;
    }

    /**
     * Return the cookie lifetime
     *
     * @return int
     */
    public function getCookieLifetime()
    {
        return Mage::getModel('core/cookie')->getLifetime() * 1000;
    }
}