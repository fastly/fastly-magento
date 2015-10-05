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

class Fastly_CDN_Model_Esi_Processor_GeoIp_Getcountry extends Fastly_CDN_Model_Esi_Processor_Abstract
{
    /**
     * create esi tag
     * this is a fallback if GeoIP is not handled by fastlyCDN
     *
     * @return string
     */
    public function getHtml()
    {
        $output        = '';
        $clientCountry = false;

        if ($clientIP = Mage::helper('core/http')->getRemoteAddr()) {
            // reduce multiple ip list to only first ip
            if (strpos($clientIP, ',')) {
                list($clientIP) = explode(',', $clientIP);
            }

            // try to get current country code
            if (function_exists('geoip_country_code_by_name')) {
                // by ip address
                $clientCountry = @geoip_country_code_by_name($clientIP);
            } else {
                // try to use the accepted language instead
                // this is based on customer browser settings
                // !!! this is not the country but the language !!!
                $clientCountry = $this->_getAcceptedLanguages();
            }
        }

        if($clientCountry) {
            // normalize client country to ISO 3166
            if(!is_array($clientCountry)) {
                $clientCountry = array($clientCountry);
            }
            $countries = array();
            foreach($clientCountry as $country) {
                $country = substr($country, 0, 2);
                if (!in_array($country, $countries)) {
                   $countries[] = $country;
                }
            }

            // build ESI tag
            $esiUrl = Mage::app()->getStore()->getUrl(
                'fastlycdn/esi/getcountryaction',
                array(
                    '_query' => array(
                        'country_code' => strtoupper($countries[0])
                    ),
                    '_nosid' => true
                )
            );
            $output = '<esi:include src="' . $esiUrl . '"/>';
        }

        return $output;
    }

    /**
     * get the list of accepted languages from the browser
     *
     * @return array
     */
    protected function _getAcceptedLanguages()
    {
        $acceptedLanguages = array();

        if ($acceptLanguage = Mage::helper('core/http')->getHttpAcceptLanguage()) {
            // iterate over accepted languages
            $languageList = explode(',', $acceptLanguage);
            foreach ($languageList as $accept) {
                // extract language parts
                // we only deal with the primary language tag
                if (preg_match('/([a-z]+)(;q=([0-9.]+))?/', trim($accept), $parts)) {
                    $acceptedLanguages[] = $parts[1];
                    $quality[] = (isset($parts[3]) ? (float)$parts[3] : 1.0);
                }
            }

            // order the codes by quality
            array_multisort($quality, SORT_NUMERIC, SORT_DESC, $acceptedLanguages);
        }

        return $acceptedLanguages;
    }
}
