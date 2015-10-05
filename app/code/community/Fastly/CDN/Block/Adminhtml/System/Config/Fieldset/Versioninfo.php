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

class Fastly_CDN_Block_Adminhtml_System_Config_Fieldset_Versioninfo
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $info = '<fieldset class="config">' .
            Mage::helper('fastlycdn')->__(
            	'Fastly CDN version: %s',
                Mage::getConfig()->getNode('modules/Fastly_CDN/version')
            ) . '<br />' .
            Mage::helper('fastlycdn')->__(
                'To contact Fastly support please click <a href="%s">here</a>.',
                'mailto:support@fastly.com?subject=[Magento%20Module%20Support]' .
                '&body=Installed%20Version%20' .Mage::getConfig()->getNode('modules/Fastly_CDN/version')
            ) .
        '</fieldset>';

        return $info;
    }
}
