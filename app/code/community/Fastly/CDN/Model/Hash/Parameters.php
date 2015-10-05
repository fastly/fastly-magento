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

/**
 * The class encapsulating the hash parameters
 *
 * Class Fastly_CDN_Model_Hash_Parameters
 */
class Fastly_CDN_Model_Hash_Parameters
{
    private $_domains = array();
    private $_type;
    private $_regexp;

    /**
     * @return array
     */
    public function getDomains()
    {
        return $this->_domains;
    }

    /**
     * @param array $domains
     *
     * @return $this
     */
    public function setDomains(array $domains)
    {
        $this->_domains = $domains;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return !empty($this->_type) ? $this->_type : '.*';
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getRegexp()
    {
        return !empty($this->_regexp) ? $this->_regexp : '.*';
    }

    /**
     * @param string $regexp
     *
     * @return $this
     */
    public function setRegexp($regexp)
    {
        $this->_regexp = $regexp;
        return $this;
    }

    public function isWildcard()
    {
        return empty($this->_domains) && empty($this->_regexp) && empty($this->_type);
    }
}
