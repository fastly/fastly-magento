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

class Fastly_CDN_Block_Adminhtml_System_Config_Fieldset_CmsMap
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected $_addRowButtonHtml = array();
    protected $_removeRowButtonHtml = array();

    /**
     * Returns HTML snippet to select geo IP cms block
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $html = '<table id="fastlycdn_geoip_map_cmsblock_template" style="display:none;">';
        $html .= $this->_getRowTemplateHtml(-1);
        $html .= '</table>';

        $html .= '<div class="grid">';
        $html .= '<table class="border" cellpadding="0" cellspacing="0" id="fastlycdn_geoip_map_cmsblock">';

        $html .= '<tr class="headings">';
        $html .= '<th>'.$this->__('Country').'</th>';
        $html .= '<th>'.$this->__('Static Cms Block').'</th>';
        $html .= '<th>&nbsp;</th>';
        $html .= '</tr>';

        if ($this->_getValue('cms')) {
            foreach ($this->_getValue('cms') as $i=>$f) {
                if ($i) {
                    $html .= $this->_getRowTemplateHtml($i);
                }
            }
        }
        $html .= '</table>';
        $html .= $this->_getAddRowButtonHtml('fastlycdn_geoip_map_cmsblock',
            'fastlycdn_geoip_map_cmsblock_template', $this->__('Add mapping'));
        $html .= '</div>';

        return $html;
    }

    /**
     * Return row to add new cms block
     *
     * @param int $i
     * @return string
     */
    protected function _getRowTemplateHtml($i=0)
    {
        $html = '<tr>';

        $html .= '<td>';
        $html .= '<input name="'.$this->getElement()->getName().'[country][]" style="width:40px" value="'.$this->_getValue('country/'.$i).'" />';
        $html .= '</td>';

        $html .= '<td>';
        $html .= '<select name="'.$this->getElement()->getName().'[cms][]">';
        $html .= '<option value="">'.$this->__('* Select Static Cms Block').'</option>';
        foreach ($this->_getCmsBlocks() as $cmsBlock) {
            $html .= '<option value="'.$cmsBlock['value'].'" '.$this->_getSelected('cms/'.$i, $cmsBlock['value']).' >'.$cmsBlock['label'].'</option>';
        }
        $html .= '</select>';
        $html .= '</td>';

        $html .= '<td>';
        $html .= $this->_getRemoveRowButtonHtml();
        $html .= '</td>';

        $html .= '</tr>';

        return $html;
    }

    /**
     * Returns available cms blocks
     *
     * @return mixed
     */
    protected function _getCmsBlocks()
    {
        return Mage::getResourceModel('cms/block_collection')->load()->toOptionArray();
    }

    /**
     * check if element is disabled
     *
     * @return string
     */
    protected function _getDisabled()
    {
        return $this->getElement()->getDisabled() ? ' disabled' : '';
    }

    protected function _getValue($key)
    {
        return $this->getElement()->getData('value/'.$key);
    }

    /**
     * Returns id an entry is selected
     *
     * @param $key
     * @param $value
     * @return string
     */
    protected function _getSelected($key, $value)
    {
        return $this->getElement()->getData('value/'.$key)==$value ? 'selected="selected"' : '';
    }

    /**
     * Returns buttons to add new entry
     *
     * @param $container
     * @param $template
     * @param string $title
     * @return mixed
     */
    protected function _getAddRowButtonHtml($container, $template, $title='Add')
    {
        if (!isset($this->_addRowButtonHtml[$container])) {
            $this->_addRowButtonHtml[$container] = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('add '.$this->_getDisabled())
                ->setLabel($this->__($title))
                ->setOnClick("Element.insert($('".$container."'), {bottom: $('".$template."').innerHTML})")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_addRowButtonHtml[$container];
    }

    /**
     * Returns button to remove an entry
     *
     * @param string $selector
     * @param string $title
     * @return array
     */
    protected function _getRemoveRowButtonHtml($selector='tr', $title='Delete')
    {
        if (!$this->_removeRowButtonHtml) {
            $this->_removeRowButtonHtml = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('delete v-middle '.$this->_getDisabled())
                ->setLabel($this->__($title))
                ->setOnClick("Element.remove($(this).up('".$selector."'))")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_removeRowButtonHtml;
    }
}
