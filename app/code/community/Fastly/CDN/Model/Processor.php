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

class Fastly_CDN_Model_Processor
{
    /**
     * This is only a dummy function at the moment to  sanitize Cache-
     * Control headers on FPC hits. It doesn't do what might be expected
     * (retrieve cached content without ramping up the whole application
     * stack), but it is the only way to hook in our logic.
     * 
     * This method is called at the very beginning of Magento from
     * Mage_Corel_Model_App::run() ->
     * Mage_Core_Model_Cache::processRequest().
     * 
     * @param string $content
     * @return string | false
     */
    public function extractContent($content)
    {
        return $content;
    }
}