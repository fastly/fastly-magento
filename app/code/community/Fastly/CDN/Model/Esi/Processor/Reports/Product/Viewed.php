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

class Fastly_CDN_Model_Esi_Processor_Reports_Product_Viewed extends Fastly_CDN_Model_Esi_Processor_Abstract
{
    protected function _initBlock($block)
    {
        // assign product ids to block
        if ($block instanceof Mage_Core_Block_Abstract) {
            $listData = $this->_getHelper()->getEsiParam();
 	 	    if (empty($listData) === false) {
                $productIds = $listData->list;

                // filter current product ID from list
                if ($productId = $this->getProductId()) {
                    $key = array_search($productId, $productIds);
                    unset($productIds[$key]);
                }
 	 	        $block->setProductIds($productIds);
            }
        }
    }
}