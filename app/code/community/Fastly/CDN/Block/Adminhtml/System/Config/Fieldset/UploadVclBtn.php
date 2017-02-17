<?php

class Fastly_CDN_Block_Adminhtml_System_Config_Fieldset_UploadVclBtn extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fastlycdn/system/config/upload_vcl_btn.phtml');
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
                'id'        => "fastly_upload_vcl_btn",
                'label'     => $this->helper('adminhtml')->__('Upload VCL to Fastly'),
                'onclick'   => "Fastly.initDialog('vcl-upload-form'); return false;"
            ));

        return $button->toHtml();
    }
}