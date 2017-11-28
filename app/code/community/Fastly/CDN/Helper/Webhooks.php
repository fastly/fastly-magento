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

class Fastly_CDN_Helper_Webhooks extends Mage_Core_Helper_Abstract
{
    const XML_PATH_FASTLY_CDN_WEBHOOKS_ENABLED          = 'fastlycdn/webhooks/enabled';
    const XML_PATH_FASTLY_CDN_WEBHOOKS_URL              = 'fastlycdn/webhooks/endpoint';
    const XML_PATH_FASTLY_CDN_WEBHOOKS_USERNAME         = 'fastlycdn/webhooks/username';
    const XML_PATH_FASTLY_CDN_WEBHOOKS_CHANNEL          = 'fastlycdn/webhooks/channel';
    const XML_PATH_FASTLY_CDN_WEBHOOKS_MESSAGE_PREFIX   = 'fastlycdn/webhooks/message_prefix';

    /**
     * Check if webhooks are enabled
     * @return bool
     */
    public function isEnabled()
    {
        return Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_WEBHOOKS_ENABLED);
    }

    /**
     * Get webhooks endpoint url
     * @return bool
     */
    public function getWebhookUrl()
    {
        return Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_WEBHOOKS_URL);
    }

    /**
     * Get webhooks username
     * @return bool
     */
    public function getWebookUsername()
    {
        return Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_WEBHOOKS_USERNAME);
    }

    /**
     * Get webhooks channel
     * @return bool
     */
    public function getWebHookChannel()
    {
        return Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_WEBHOOKS_CHANNEL);
    }

    /**
     * Get message prefix for webhooks message
     * @return bool
     */
    public function getWebhookMessagePrefix()
    {
        return Mage::getStoreConfig(self::XML_PATH_FASTLY_CDN_WEBHOOKS_MESSAGE_PREFIX);
    }

    /**
     * Send message to Slack channel
     *
     * @param $message
     */
    public function sendWebHook($message)
    {
        $urlEndpoint = $this->getWebhookUrl();
        $username = $this->getWebookUsername();
        $channel = '#' . $this->getWebHookChannel();
        $messagePrefix = $this->getWebhookMessagePrefix();
        $storeName = Mage::app()->getStore()->getName();
        $storeUrl = Mage::app()->getStore()->getUrl();
        $message =  $messagePrefix . $message.' on <'.$storeUrl.'|Store> | '.$storeName;
        $headers = array('Content-type: application/json');

        $body = json_encode(array(
            'text' => $message,
            'username' => $username,
            'channel' => $channel,
            'icon_emoji' => ':airplane:'
        ));

        $curl = new Varien_Http_Adapter_Curl();
        $curl->setConfig(array(
            'timeout'   => 2
        ));
        $curl->write(Zend_Http_Client::POST, $urlEndpoint, '1.1', $headers, $body);
        if ($curl->read()) {
            if ($curl->getInfo(CURLINFO_HTTP_CODE) != 200) {
                $response = $curl->read();
                Mage::log('Failed to send message to the following Webhook: ' . $urlEndpoint . ' - Response: ' . $response);
            }
        }

        $curl->close();
    }
}
