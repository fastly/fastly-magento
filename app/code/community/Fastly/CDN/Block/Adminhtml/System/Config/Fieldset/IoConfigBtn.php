<?php

class Fastly_CDN_Block_Adminhtml_System_Config_Fieldset_IoConfigBtn extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fastlycdn/system/config/io_config_btn.phtml');
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
                'id'        => "io_config_btn",
                'label'     => $this->helper('adminhtml')->__('Configure'),
                'onclick'   => "Fastly.initDialog('io-config-form'); return false;"
            ));

        return $button->toHtml();
    }
}