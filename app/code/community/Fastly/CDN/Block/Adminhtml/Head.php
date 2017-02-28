<?php

class Fastly_CDN_Block_Adminhtml_Head extends Mage_Adminhtml_Block_Template
{
    protected function _prepareLayout()
    {
        if (Mage::app()->getRequest()->getParam('section') == 'fastlycdn') {
            $this->getLayout()->getBlock('head')->addItem('skin_css', 'css/fastlycdn/fastly.css');
            $this->getLayout()->getBlock('head')->addItem('skin_js', 'js/fastlycdn/fastly.js');
            $this->getLayout()->getBlock('head')->addCss('lib/prototype/windows/themes/magento.css');
        }

        return parent::_prepareLayout();
    }
}