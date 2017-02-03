<?php

class Fastly_CDN_Model_Statistic extends Mage_Core_Model_Abstract
{
    /**
     * Fastly INSTALLED Flag
     */
    const FASTLY_INSTALLED_FLAG = 'installed';
    /**
     * Fastly CONFIGURED Flag
     */
    const FASTLY_CONFIGURED_FLAG = 'configured';
    /**
     * Fastly NOT_CONFIGURED Flag
     */
    const FASTLY_NOT_CONFIGURED_FLAG = 'not_configured';
    const FASTLY_VALIDATED_FLAG = 'validated';
    const FASTLY_NON_VALIDATED_FLAG = 'non_validated';

    const FASTLY_CONFIGURATION_FLAG = 'configuration';
    const FASTLY_VALIDATION_FLAG = 'validation';

    const FASTLY_MODULE_NAME = 'Fastly_Cdn';
    const CACHE_TAG = 'fastly_cdn_statistic';
    const FASTLY_GA_TRACKING_ID = 'UA-89025888-2';
    const GA_API_ENDPOINT = 'https://www.google-analytics.com/collect';
    const GA_HITTYPE_PAGEVIEW = 'pageview';
    const GA_HITTYPE_EVENT = 'event';
    const GA_PAGEVIEW_URL = 'http://fastly.com/';
    const GA_FASTLY_SETUP = 'Fastly Setup';

    protected $_cid;
    protected $_GAReqData = [];

    /**
     * @var \Fastly_CDN_Helper_Data
     */
    protected $_helper;

    protected function _construct()
    {
        $this->_init('fastlycdn/statistic');
    }

    /**
     * Returns GA API Endpoint
     *
     * @return string
     */
    public function getApiEndpoint()
    {
        return self::GA_API_ENDPOINT;
    }

    /**
     * Returns GA Tracking ID
     *
     * @return string
     */
    public function getGATrackingId()
    {
        return self::FASTLY_GA_TRACKING_ID;
    }

    /**
     * Returns Control Model instance
     *
     * @return false|Fastly_CDN_Model_Control|Mage_Core_Model_Abstract
     */
    public function getControl()
    {
        return Mage::getModel('fastlycdn/control');
    }

    /**
     * Returns Data Helper class instance
     *
     * @return Fastly_CDN_Helper_Data|Mage_Core_Helper_Abstract
     */
    public function getHelper()
    {
        if(is_null($this->_helper))
        {
            $helper = Mage::helper('fastlycdn');
            $this->_helper = $helper;
        }

        return $this->_helper;
    }

    /**
     * Prepares GA data for request
     *
     * @return array
     */
    protected function _prepareGAReqData(array $additionalParams = [])
    {
        if(!empty($this->_GAReqData)) {
            return $this->_GAReqData;
        }

        $mandatoryReqData = [];
        $mandatoryReqData['v'] = 1;
        $mandatoryReqData['tid'] = $this->getGATrackingId();
        if(!$this->_cid) {
            $this->_cid = $this->getHelper()->getCID();
        }
        $mandatoryReqData['cid'] = $this->_cid;
        $mandatoryReqData['uid'] = $this->_cid;
        // Magento version
        $mandatoryReqData['ua'] = Mage::getVersion();

        $countryCode = Mage::getStoreConfig('general/country/default');
        if($countryCode) {
            $country = Mage::getModel('directory/country')->loadByCode($countryCode);
            // Country code
            $mandatoryReqData['geoid'] = $countryCode;
        }

        $customVars = $this->_prepareCustomVariables();

        $this->_GAReqData = array_merge($mandatoryReqData, $customVars);

        return $this->_GAReqData;
    }

    /**
     * Returns Website name
     *
     * @return string $websiteName
     */
    public function getWebsiteName()
    {
        $websites = Mage::app()->getWebsites(true);

        $websiteName = 'Not set.';

        foreach($websites as $website)
        {
            if($website->getIsDefault()) {
                $websiteName = $website->getName();
            }
        }

        return $websiteName;
    }

    /**
     * Checks if API key is valid
     *
     * @return bool $isApiKeyValid
     */
    public function isApiKeyValid()
    {
        $apiKey = $this->getHelper()->getApiKey();
        $serviceId = $this->getHelper()->getServiceId();
        $isApiKeyValid = $this->getControl()->testConnection($serviceId, $apiKey);

        return (bool)$isApiKeyValid;
    }

    /**
     * Prepares GA custom variables
     *
     * @return array
     */
    protected function _prepareCustomVariables()
    {
        $customVars =  [
            // Service ID
            'cd1'   =>  $this->getHelper()->getServiceId(),
            // isAPIKeyValid
            'cd2'   =>  ($this->isApiKeyValid()) ? 'yes' : 'no',
            // Website name
            'cd3'   =>  $this->getWebsiteName(),
            // Site domain
            'cd4'   =>  $_SERVER['HTTP_HOST'],
            //
            //'cd5'   =>  $this->getSiteLocation()
        ];

        return $customVars;
    }

    /**
     * Returns Site location, if available
     *
     * @return string
     */
    public function getSiteLocation()
    {
        $countryId = $this->_scopeConfig->getValue('general/store_information/country_id');
        if($countryId) {
            $country = $this->_countryInformation->getCountryInfo($countryId);
            $countryName = $country->getFullNameEnglish();
        } else {
            $countryName = 'Unknown country';
        }

        $regionId = $this->_scopeConfig->getValue('general/store_information/region_id');
        $regionName = 'Unknown region';
        if($regionId) {
            $region = $this->_regionFactory->create();
            $region = $region->load($regionId);
            if($region->getId()) {
                $regionName = $region->getName();
            }
        }

        $postCode = $this->_scopeConfig->getValue('general/store_information/postcode');
        if(!$postCode) {
            $postCode = 'Unknown zip code';
        }

        return $countryName .' | '.$regionName.' | '.$postCode;
    }

    /**
     * Generate GA CID
     *
     * @return string
     */
    public function generateCid() {
        $this->_cid =  sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        return $this->_cid;
    }

    /**
     * Get Google Analytics mandatory data
     *
     * @return array
     */
    public function getGAReqData()
    {
        return $this->_prepareGAReqData();
    }

    /**
     * Sends request to GA that the Fastly module is installed
     *
     * @return bool|string $result
     */
    public function sendInstalledReq()
    {
        $pageViewParams = [
            'dl'    =>  self::GA_PAGEVIEW_URL . self::FASTLY_INSTALLED_FLAG,
            'dh'    =>  preg_replace('#^https?://#', '', rtrim(self::GA_PAGEVIEW_URL,'/')),
            'dp'    =>  '/'.self::FASTLY_INSTALLED_FLAG,
            'dt'    =>  ucfirst(self::FASTLY_INSTALLED_FLAG),
            't'     =>  self::GA_HITTYPE_PAGEVIEW
        ];

        $this->_sendReqToGA($pageViewParams, self::GA_HITTYPE_PAGEVIEW);

        $eventParams = [
            'ec'    =>  self::GA_FASTLY_SETUP,
            'ea'    =>  'Fastly '.self::FASTLY_INSTALLED_FLAG,
            'el'    =>  $this->getWebsiteName(),
            'ev'    =>  0,
            't'     =>  self::GA_HITTYPE_EVENT
        ];

        $result = $this->_sendReqToGA(array_merge($pageViewParams, $eventParams));

        return $result;
    }

    /**
     * Sends request to GA every time the Test connection button is pressed
     *
     * @param $validatedFlag
     * @return bool|string
     */
    public function sendValidationRequest($validatedFlag)
    {
        if($validatedFlag) {
            $validationState = self::FASTLY_VALIDATED_FLAG;
        } else {
            $validationState = self::FASTLY_NON_VALIDATED_FLAG;
        }

        $pageViewParams = [
            'dl'    =>  self::GA_PAGEVIEW_URL . $validationState,
            'dh'    =>  preg_replace('#^https?://#', '', rtrim(self::GA_PAGEVIEW_URL,'/')),
            'dp'    =>  '/'.$validationState,
            'dt'    =>  ucfirst($validationState),
            't'     =>  self::GA_HITTYPE_PAGEVIEW
        ];

        $this->_sendReqToGA($pageViewParams);

        $eventParams = [
            'ec'    =>  self::GA_FASTLY_SETUP,
            'ea'    =>  'Fastly '.$validationState,
            'el'    =>  $this->getWebsiteName(),
            'ev'    =>  $this->daysFromInstallation(),
            't'     =>  self::GA_HITTYPE_EVENT
        ];

        $result = $this->_sendReqToGA(array_merge($pageViewParams, $eventParams));

        return $result;
    }

    /**
     * Sends Fastly configured\not_configured request to GA
     *
     * @param $configuredFlag
     * @return bool
     */
    public function sendConfigurationRequest($configuredFlag)
    {
        if($configuredFlag) {
            $configuredState = self::FASTLY_CONFIGURED_FLAG;
        } else {
            $configuredState = self::FASTLY_NOT_CONFIGURED_FLAG;
        }

        $pageViewParams = [
            'dl'    =>  self::GA_PAGEVIEW_URL . $configuredState,
            'dh'    =>  preg_replace('#^https?://#', '', rtrim(self::GA_PAGEVIEW_URL,'/')),
            'dp'    =>  '/'.$configuredState,
            'dt'    =>  ucfirst($configuredState),
            't'     =>  self::GA_HITTYPE_PAGEVIEW
        ];

        $this->_sendReqToGA($pageViewParams);

        $eventParams = [
            'ec'    =>  self::GA_FASTLY_SETUP,
            'ea'    =>  'Fastly '.$configuredState,
            'el'    =>  $this->getWebsiteName(),
            'ev'    =>  $this->daysFromInstallation(),
            't'     =>  self::GA_HITTYPE_EVENT
        ];

        $result = $this->_sendReqToGA(array_merge($pageViewParams, $eventParams));

        return $result;
    }

    public function daysFromInstallation()
    {
        $stat = $this->getCollection()->getStatByAction(self::FASTLY_INSTALLED_FLAG);

        if(!$stat->getCreatedAt()) {
            return null;
        }
        $installDate = date_create($stat->getCreatedAt());
        $currentDate = date_create(Varien_Date::now());

        $dateDiff = date_diff($installDate, $currentDate);

        return $dateDiff->days;
    }

    /**
     * @param string $body
     * @param string $method
     * @param string $uri
     * @return bool|Zend_Http_Response
     */
    protected function _sendReqToGA($body = '', $method = \Zend_Http_Client::POST, $uri = self::GA_API_ENDPOINT)
    {
        $reqGAData = (array)$this->getGAReqData();

        if($body != '' && is_array($body) && !empty($body)) {
            $body = array_merge($reqGAData, $body);
        }

        try {

            $body = http_build_query($body);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_exec($ch);

            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($response != '200') {
                throw new Exception('Return status ' . $response->getStatus());
            }

            return true;
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Stat error: (' . $e->getMessage() . ').');
            return false;
        }
    }

}