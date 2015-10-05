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

class Fastly_CDN_Helper_Tags extends Mage_Core_Helper_Abstract
{
    const SURROGATE_KEY_PRODUCT_PREFIX  = 'PRD';
    const SURROGATE_KEY_CATEGORY_PREFIX = 'CAT';
    const SURROGATE_KEY_CMSPAGE_PREFIX  = 'CMP';
    const SURROGATE_KEY_CMSBLOCK_PREFIX = 'CMB';
    const SURROGATE_KEY_STORE_PREFIX    = 'STO';
    const SURROGATE_KEY_HEADER_NAME     = 'Surrogate-Key';

    /**
     * Return cache tag ids
     *
     * @param $cacheTags
     * @param $name
     * @return array
     */
    public function extractTagIds($cacheTags, $name)
    {
        $idList = array();

        // inspect all tags
        foreach ($cacheTags as $cacheTag) {
            // name found
            if (strpos($cacheTag, $name) !== false) {
                // tag is divided by _
                $tagParts = explode('_', $cacheTag);
                // id is the last part
                $id = array_pop($tagParts);
                // store id
                if (is_numeric($id)) {
                    $idList[] = $id;
                }
            }
        }

        // return list of ids
        return $idList;
    }
}
