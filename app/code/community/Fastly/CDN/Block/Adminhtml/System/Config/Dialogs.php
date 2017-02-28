<?php

class Fastly_CDN_Block_Adminhtml_System_Config_Dialogs extends Mage_Core_Block_Template
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('fastlycdn/system/config/dialogs.phtml');
    }
}