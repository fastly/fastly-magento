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

class Fastly_CDN_Model_Config
{
    /**
     * Magento module prefix used for naming vcl snippets, condition and request
     */
    const FASTLY_MAGENTO_MODULE = 'magentomodule';

    /**
     * Magento Error Page Response Object Name
     */
    const ERROR_PAGE_RESPONSE_OBJECT = self::FASTLY_MAGENTO_MODULE.'_error_page_response_object';

    protected $_esiTags = null;
    
    public function getEsiTags()
    {
        if (is_null($this->_esiTags)) {
            foreach (Mage::getConfig()->getNode('fastlycdn_esi_tags')->children() as $key => $config) {
                $this->_esiTags[(string)$key] = array(
                    'block'	     => (string)$config->block,
                    'esiTag'     => (string)$config->esi_tag,
                    'processor'  => (string)$config->processor
                );
            }
        }
        return $this->_esiTags;
    }
    
    /**
     * Get ESI Tag model by block type
     * 
     * @param string $blockType
     * @return string
     */
    public function getEsiTagClassByBlockType($blockType)
    {
        $config = $this->getEsiTags();
        foreach ($config as $esiTag) {
            if ($esiTag['block'] == $blockType) {
                return $esiTag['esiTag'];
            }
        }
        return false;
    }

    /**
     * Get ESI processor by config key
     * 
     * @param string $configKey
     * @return 
     */
    public function getEsiProcessorConfig($configKey)
    {
        $esiTags = $this->getEsiTags();

        if (isset($esiTags[$configKey])) {
            return $esiTags[$configKey];
        }
        return false;
    }

    public function getVclSnippets($path = 'vcl_snippets', $specificFile = null)
    {
        $snippetsData = array();

        $moduleEtcPath = Mage::getModuleDir('etc', 'Fastly_CDN') . DS . $path . DS;
        $fileReader = new Varien_Io_File();
        $fileReader->open(array('path' => $moduleEtcPath));
        if (!$specificFile) {
            $snippets = $fileReader->ls();
            if(is_array($snippets))
            {
                foreach ($snippets as $snippet) {
                    if ($snippet['filetype'] != 'vcl') {
                        continue;
                    }
                    $snippetFilePath = $moduleEtcPath . $snippet['text'];
                    $type = explode('.', $snippet['text'])[0];
                    $snippetsData[$type] = $fileReader->read($snippetFilePath);
                }
            }
        } else {
            $snippetFilePath = $moduleEtcPath . '/' . $specificFile;
            $type = explode('.', $specificFile)[0];
            $snippetsData[$type] = $fileReader->read($snippetFilePath);
        }

        return $snippetsData;
    }
}