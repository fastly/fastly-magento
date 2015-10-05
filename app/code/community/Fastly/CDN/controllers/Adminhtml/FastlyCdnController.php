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

class Fastly_CDN_Adminhtml_FastlyCdnController extends Mage_Adminhtml_Controller_Action
{
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * purge by store
     */
    public function cleanStoreAction()
    {
        try {
            if (Mage::helper('fastlycdn')->isEnabled()) {
                // check if store is given
                $storeId = $this->getRequest()->getParam('stores', false);
                if (!$storeId) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid store "%s".', $storeId));
                }

                // clean Fastly CDN
                $key = Fastly_CDN_Helper_Tags::SURROGATE_KEY_STORE_PREFIX . $storeId;
                Mage::getModel('fastlycdn/control')->cleanBySurrogateKey($key);

                $this->_getSession()->addSuccess(
                    Mage::helper('fastlycdn')->__('The Fastly CDN has been cleaned.')
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('fastlycdn')->__('An error occurred while clearing the Fastly CDN.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * purge by content type
     */
    public function cleanContentTypeAction()
    {
        try {
            if (Mage::helper('fastlycdn')->isEnabled()) {

                // check if content type is given
                $contentType = $this->getRequest()->getParam('content_types', false);
                if (!$contentType) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid content type "%s".', $contentType));
                }

                // clean Fastly CDN
                Mage::getModel('fastlycdn/control')->cleanBySurrogateKey($contentType);

                $this->_getSession()->addSuccess(
                    Mage::helper('fastlycdn')->__('The Fastly CDN has been cleaned.')
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('fastlycdn')->__('An error occurred while clearing the Fastly CDN.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * purge a single url
     */
    public function quickPurgeAction()
    {
        try {
            if (Mage::helper('fastlycdn')->isEnabled()) {

                // check if url is given
                $url = $this->getRequest()->getParam('quick_purge_url', false);
                if (!$url) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid URL "%s".', $url));
                }

                // get url parts
                extract(parse_url($url));

                // check if host is set
                if (!isset($host)) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid URL "%s".', $url));
                }

                // check if host is one of magento's
                $domainList = Mage::helper('fastlycdn/cache')->getStoreDomainList();
                if (!in_array($host, explode('|', $domainList))) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid domain "%s".', $host));
                }

                // build uri to purge
                $uri = $scheme . '://'
                    . $host;

                if (isset($path)) {
                    $uri .= $path;
                }
                if (isset($query)) {
                    $uri .= '\?';
                    $uri .= $query;
                }
                if (isset($fragment)) {
                    $uri .= '#';
                    $uri .= $fragment;
                }

                // purge uri
                Mage::getModel('fastlycdn/control')->cleanUrl($uri);

                $this->_getSession()->addSuccess(
                    Mage::helper('fastlycdn')->__('The URL\'s "%s" cache has been cleaned.', $url)
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('fastlycdn')->__('An error occurred while clearing the Fastly CDN.')
            );
        }
        $this->_redirect('*/cache/index');
    }
}
