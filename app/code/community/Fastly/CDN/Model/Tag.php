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

class Fastly_CDN_Model_Tag
{
    protected static $_tagStorage = array();

    /**
     * add tag ids to a tag container
     *
     * @param string $container
     * @param array $ids
     */
    public static function addTagIds($container, $ids)
    {
        self::_initContainer($container);
        self::$_tagStorage[$container] = array_merge(self::$_tagStorage[$container], $ids);
    }

    /**
     * get tag ids for a container
     *
     * @param string $container
     * @return array
     */
    public static function getTagsIds($container)
    {
        self::_initContainer($container);
        return array_unique(self::$_tagStorage[$container]);

    }

    /**
     * create container if not exists
     *
     * @param string $container
     */
    protected static function _initContainer($container)
    {
        if (empty(self::$_tagStorage[$container])) {
            self::$_tagStorage[$container] = array();
        }
    }
}
