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

class Fastly_CDN_Block_Adminhtml_System_Config_Fieldset_RouteTTL
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    public function __construct()
    {
        $this->addColumn(
            'regexp', array(
                'label' => Mage::helper('adminhtml')->__('Route'),
                'style' => 'width:120px')
        );
        $this->addColumn(
            'value', array(
                'label' => Mage::helper('adminhtml')->__('TTL'),
                'style' => 'width:120px')
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add route');
        parent::__construct();
    }

    protected function _toHtml()
    {
        return '<div id="fastlycdn_general_routes_ttl">' . parent::_toHtml() . '</div>';
    }
}
