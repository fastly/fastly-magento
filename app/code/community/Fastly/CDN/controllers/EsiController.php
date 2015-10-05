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

class Fastly_CDN_EsiController extends Mage_Core_Controller_Front_Action
{
    const DEFAULT_PROCESSOR_NAME = 'Fastly_CDN_Model_Esi_Processor_Default';

    public function processorAction()
    {
        Mage::register('ESI_PROCESSING', true, true);

        $content = '';

        if ($action = $this->_getHelper()->getActionFromRequest()) {

            $processor = $this->_getHelper()->getProcessorByAction($action);
            if ($processor === false) {
                $processor = self::DEFAULT_PROCESSOR_NAME;
            }
            /* @var $processor Fastly_CDN_Model_Esi_Processor_Abstract */

            $this->_prepareLayout();

            $requestParams = $this->getRequest()->getParams();
            $this->_emulateSecureRequest($requestParams);

            $processorModel = Mage::getModel($processor, $requestParams);
            // get block content
            if ($processorModel instanceof Fastly_CDN_Model_Esi_Processor_Abstract) {
                $content = $processorModel->getHtml();
            }

            Mage::helper('fastlycdn/cache')->setTtlHeader($processorModel->getEsiBlockTtl($action));
            Mage::register(Fastly_CDN_Helper_Cache::REGISTRY_VAR_FASTLY_CDN_CONTROL_HEADERS_SET_FLAG, 1);

        } else {
            $content = Mage::helper('fastlycdn')->__('No Fastly CDN ESI action given');
        }

        $this->getResponse()->setBody($content);
    }

    /**
     * @return Fastly_CDN_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('fastlycdn');
    }

    /**
     * Layout necessary layout handles
     *
     * @return void
     */
    protected function _prepareLayout()
    {
        if ($layoutHandles = $this->getRequest()->getParam($this->_getHelper()->getLayoutHandlesParam())) {
            // set fake category and product
            Mage::register('current_category', Mage::getModel('catalog/category'));
            Mage::register('product', Mage::getModel('catalog/product'));

            // add handles
            $update = $this->getLayout()->getUpdate();
            $existingHandles = $update->getHandles();

            foreach (explode(',', $layoutHandles) as $handle) {
                if (!in_array($handle, $existingHandles)) {
                    $update->addHandle($handle);
                }
            }

            // update layout blocks
            $this->loadLayoutUpdates();
            $this->generateLayoutXml()->generateLayoutBlocks();
        } else {
            $this->loadLayout();
        }
    }

    /**
     * If is_secure param is set emulate secure request to get correct URLs in block
     *
     * @see Mage_Core_Model_Store::isCurrentlySecure
     * @param array request parameters
     * @return void
     */
    protected function _emulateSecureRequest(Array $params)
    {
        $isSecureParam = $this->_getHelper()->getIsSecureParam();
        if (isset($params[$isSecureParam]) && $params[$isSecureParam] === '1') {
            // hard set HTTPS server environment variable to "on"
            $_SERVER['HTTPS'] = 'on';
        }
    }
}