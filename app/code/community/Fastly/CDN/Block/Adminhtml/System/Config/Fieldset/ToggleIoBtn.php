<?php

class Fastly_CDN_Block_Adminhtml_System_Config_Fieldset_ToggleIoBtn extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fastlycdn/system/config/toggle_io_btn.phtml');
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id'        => "fastly_toggle_io_btn",
                'label'     => $this->helper('adminhtml')->__('Enable/Disable'),
                'onclick'   => "Fastly.initDialog('toggle-io-form'); return false;"
            ));

        return $button->toHtml();
    }
}