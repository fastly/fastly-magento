<?php

class Fastly_CDN_Model_Resource_Mysql4_Statistic_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('fastlycdn/statistic');
    }

    /**
     * @param $action
     * @return Varien_Object
     */
    public function getStatByAction($action)
    {
        return $this->addFieldToFilter('action', $action)->setOrder('created_at', 'DESC')->getFirstItem();
    }
}