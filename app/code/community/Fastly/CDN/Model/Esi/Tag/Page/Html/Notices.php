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

class Fastly_CDN_Model_Esi_Tag_Page_Html_Notices extends Fastly_CDN_Model_Esi_Tag_Abstract
{
    const COOKIE_NAME = 'user_allowed_save_cookie';
    const ESI_URL     = 'fastlycdn/esi/page_html_notices';

    /**
     * Assume the html notices won't be affected by layout handles.
     *
     * @param Mage_Core_Block_Abstract $block
     * @return array|void
     */
    protected function _getLayoutHandles(Mage_Core_Block_Abstract $block)
    {
        return;
    }
}
