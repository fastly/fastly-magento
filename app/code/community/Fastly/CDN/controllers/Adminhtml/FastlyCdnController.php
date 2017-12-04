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

class Fastly_CDN_Adminhtml_FastlyCdnController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @var Fastly_CDN_Model_Statistic
     */
    protected $_statistic;


    const FORCE_TLS_SETTING_NAME = 'magentomodule_force_tls';
    /**
     * VCL error snippet path
     */
    const VCL_ERROR_SNIPPET_PATH = 'vcl_snippets_error_page';
    const VCL_ERROR_SNIPPET = 'deliver.vcl';

    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * purge by store
     */
    public function cleanStoreAction()
    {
        try {
            if (Mage::helper('fastlycdn')->isEnabled()) {
                // check if store is given
                $storeId = $this->getRequest()->getParam('stores', false);
                if (!$storeId) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid store "%s".', $storeId));
                }

                // clean Fastly CDN
                $key = Fastly_CDN_Helper_Tags::SURROGATE_KEY_STORE_PREFIX . $storeId;
                Mage::getModel('fastlycdn/control')->cleanBySurrogateKey($key);

                $this->_getSession()->addSuccess(
                    Mage::helper('fastlycdn')->__('The Fastly CDN has been cleaned.')
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('fastlycdn')->__('An error occurred while clearing the Fastly CDN.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * purge by content type
     */
    public function cleanContentTypeAction()
    {
        try {
            if (Mage::helper('fastlycdn')->isEnabled()) {

                // check if content type is given
                $contentType = $this->getRequest()->getParam('content_types', false);
                if (!$contentType) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid content type "%s".', $contentType));
                }

                // clean Fastly CDN
                Mage::getModel('fastlycdn/control')->cleanBySurrogateKey($contentType);

                $this->_getSession()->addSuccess(
                    Mage::helper('fastlycdn')->__('The Fastly CDN has been cleaned.')
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('fastlycdn')->__('An error occurred while clearing the Fastly CDN.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * purge a single url
     */
    public function quickPurgeAction()
    {
        try {
            if (Mage::helper('fastlycdn')->isEnabled()) {

                // check if url is given
                $url = $this->getRequest()->getParam('quick_purge_url', false);
                if (!$url) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid URL "%s".', $url));
                }

                // get url parts
                extract(parse_url($url));

                // check if host is set
                if (!isset($host)) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid URL "%s".', $url));
                }

                // check if host is one of magento's
                $domainList = Mage::helper('fastlycdn/cache')->getStoreDomainList();
                if (!in_array($host, explode('|', $domainList))) {
                    throw new Mage_Core_Exception(Mage::helper('fastlycdn')->__('Invalid domain "%s".', $host));
                }

                $scheme = Mage::app()->getStore()->isCurrentlySecure() ? 'https' : 'http';

                // build uri to purge
                $uri = $scheme . '://'
                    . $host;

                if (isset($path)) {
                    $uri .= $path;
                }
                if (isset($query)) {
                    $uri .= '\?';
                    $uri .= $query;
                }
                if (isset($fragment)) {
                    $uri .= '#';
                    $uri .= $fragment;
                }

                // purge uri
                Mage::getModel('fastlycdn/control')->cleanUrl($uri);

                $this->_getSession()->addSuccess(
                    Mage::helper('fastlycdn')->__('The URL\'s "%s" cache has been cleaned.', $url)
                );
            }
        }
        catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e) {
            $this->_getSession()->addException(
                $e,
                Mage::helper('fastlycdn')->__('An error occurred while clearing the Fastly CDN.')
            );
        }
        $this->_redirect('*/cache/index');
    }

    /**
     * Test Fastly service connection
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function testConnectionAction()
    {
        $apiKey = $this->getRequest()->getParam('api_key');
        $serviceId = $this->getRequest()->getParam('service_id');
        $webhooks = Mage::helper('fastlycdn/webhooks');

        $result = Mage::getModel('fastlycdn/control')->testConnection($serviceId, $apiKey);

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $statistic = $this->getStatistic();

        if(!$result) {
            $sendValidationReq = $statistic->sendValidationRequest(false);
            $this->_saveValidationState(false, $sendValidationReq);
            $webhooks->sendWebHook('Testing Connection : Failed');
            return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array('status' =>  false)));
        }

        $sendValidationReq = $statistic->sendValidationRequest(true);
        $this->_saveValidationState(true, $sendValidationReq);

        $webhooks->sendWebHook('Testing Connection : Success');
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode(array('status' =>  true)));
    }

    protected function _saveValidationState($serviceStatus, $gaRequestStatus)
    {
        $validationStat = $this->getStatistic();
        $validationStat->setAction($validationStat::FASTLY_VALIDATION_FLAG);
        $validationStat->setSent($gaRequestStatus);
        $validationStat->setState($serviceStatus);
        $validationStat->save();
    }

    public function getStatistic()
    {
        if (!$this->_statistic) {
            $this->_statistic = Mage::getModel('fastlycdn/statistic');
        }

        return $this->_statistic;
    }

    /**
     * Check service details
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function checkServiceDetailsAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $service = Mage::getModel('fastlycdn/control')->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false));
                return $this->getResponse()->setBody($jsonData);
            }

            $versions = Mage::helper('fastlycdn')->determineVersions($service->versions);

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'service' => $service,
                'active_version' => $versions['active_version'], 'next_version' => $versions['next_version']));

            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'error' => array('msg' => $e->getMessage())));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Upload VCL snippets
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function uploadVclSnippetsAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $snippets = Mage::getModel('fastlycdn/config')->getVclSnippets();

            foreach($snippets as $key => $value)
            {
                $snippetData = array('name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_'.$key, 'type' => $key, 'dynamic' => "0", 'priority' => 50, 'content' => $value);
                $status = $control->uploadSnippet($clone->number, $snippetData);

                if(!$status) {
                    $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to upload the Snippet file.'));
                    return $this->getResponse()->setBody($jsonData);
                }
            }

            $condition = array('name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_pass', 'statement' => 'req.http.x-pass', 'type' => 'REQUEST', 'priority' => 90);
            $createCondition = $control->createCondition($clone->number, $condition);

            if(!$createCondition) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create a REQUEST condition.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $request = array(
                'action' => 'pass',
                'max_stale_age' => 3600,
                'name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_request',
                'request_condition' => $createCondition->name,
                'service_id' => $service->id,
                'version' => $currActiveVersion['active_version']
            );

            $createReq = $control->createRequest($clone->number, $request);

            if(!$createReq) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create a REQUEST object.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
                Mage::helper('fastlycdn/webhooks')->sendWebHook('Upload VCL has been initiated in version ' . $clone->number . ' - activated');
            } else {
                Mage::helper('fastlycdn/webhooks')->sendWebHook('Upload VCL has been initiated in version ' . $clone->number . ' - not activated');
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'active_version' => $clone->number));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Clones and activates new version (Dictionary)
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function createDictionaryAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $dictionaryName = $this->getRequest()->getParam('dictionary_name');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $status = $control->createDictionary($clone->number, $dictionaryName);

            if(!$status) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create Dictionary.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(
                array(
                    'status' => true,
                    'active_version' => $clone->number,
                    'dictionary_name' => $status->name,
                    'dictionary_id' => $status->id
                ));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Clones and activates new version (Dictionary)
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function deleteDictionaryAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $dictionaryName = $this->getRequest()->getParam('dictionary_name');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $status = $control->deleteDictionary($clone->number, $dictionaryName);

            if(!$status) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to remove Dictionary.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'active_version' => $clone->number));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Fetches dictionary items
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function listDictionaryItemsAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $dictionaryId = $this->getRequest()->getParam('dictionary_id');

            if(!$dictionaryId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing dictionary name.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $items = $control->getDictionaryItems($dictionaryId);

            if(is_array($items) && empty($items)) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => 'empty', 'msg' => 'This dictionary has no items.'));
                return $this->getResponse()->setBody($jsonData);
            }

            if(!$items) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to fetch Dictionary items.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'items' => $items));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Add dictionary item
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function addDictionaryItemAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $dictionaryId = $this->getRequest()->getParam('dictionary_id');
            $key = $this->getRequest()->getParam('item_key');
            $value = $this->getRequest()->getParam('item_value');

            if(!$key || !$value) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Dictionary item data.'));
                return $this->getResponse()->setBody($jsonData);
            }

            if(!$dictionaryId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Dictionary name.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $params = array(
                'item_key' => $key,
                'item_value' => $value
            );

            $item = $control->addDictionaryItem($dictionaryId, $params);

            if(!$item) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to add Dictionary item.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'item' => $item));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Remove dictionary item
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function removeDictionaryItemAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $dictionaryId = $this->getRequest()->getParam('dictionary_id');
            $key = $this->getRequest()->getParam('item_key');

            if(!$key) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Dictionary item data.'));
                return $this->getResponse()->setBody($jsonData);
            }

            if(!$dictionaryId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Dictionary name.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $status = $control->removeDictionaryItem($dictionaryId, $key);

            if(!$status) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to remove Dictionary item.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Check if TLS is enabled\disabled
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function checkTlsSettingAction()
    {
        try {
            $activeVersion = $this->getRequest()->getParam('active_version');
            $req = Mage::getModel('fastlycdn/control')->getRequest($activeVersion, self::FORCE_TLS_SETTING_NAME);

            if(!$req) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'req_setting' => $req));
            return $this->getResponse()->setBody($jsonData);
        } catch (\Exception $e) {
            $jsonData = Mage::helper('core')->jsonEncode(array('status' => false, 'error' => array('msg' => $e->getMessage())));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    public function toggleTlsAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $reqName = Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_force_tls';
            $checkIfReqExist = $control->getRequest($activeVersion, $reqName);
            $snippet = Mage::getModel('fastlycdn/config')->getVclSnippets('vcl_snippets_force_tls', 'recv.vcl');

            if(!$checkIfReqExist) {
                $request = array(
                    'name' => $reqName,
                    'service_id' => $service->id,
                    'version' => $currActiveVersion['active_version'],
                    'force_ssl' => true
                );

                $createReq = $control->createRequest($clone->number, $request);

                if(!$createReq) {
                    $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create the REQUEST object.'));
                    return $this->getResponse()->setBody($jsonData);
                }

                // Add force Tls snipet
                foreach($snippet as $key => $value) {
                    $snippetData = array(
                        'name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE . '_force_tls_' . $key,
                        'type' => $key,
                        'dynamic' => "0",
                        'priority' => 10,
                        'content' => $value
                    );
                    $status = $control->uploadSnippet($clone->number, $snippetData);

                    if(!$status) {
                        $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to upload the Snippet file.'));
                        return $this->getResponse()->setBody($jsonData);
                    }
                }
                $statusMsg = 'Activated';
            } else {
                $deleteRequest = $control->deleteRequest($clone->number, $reqName);

                if(!$deleteRequest) {
                    $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to delete the REQUEST object.'));
                    return $this->getResponse()->setBody($jsonData);
                }

                // Remove force Tls snipet
                foreach($snippet as $key => $value)
                {
                    $snippetData = array('name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_'.$key, 'type' => $key, 'dynamic' => "0", 'priority' => 10, 'content' => $value);
                    $status = true;
                    if(Mage::getModel('fastlycdn/control')->getSnippet($clone->number, $snippetData['name'])) {
                        $status = $control->removeSnippet($clone->number, $snippetData);
                    }

                    if(!$status) {
                        $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to remove the Snippet file.'));
                        return $this->getResponse()->setBody($jsonData);
                    }
                }
                $statusMsg = 'Deactivated';
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
                Mage::helper('fastlycdn/webhooks')->sendWebHook('TLS :' . $statusMsg . ' | Version : ' . $clone->number . ' - activated');
            } else {
                Mage::helper('fastlycdn/webhooks')->sendWebHook('TLS :' . $statusMsg . ' | Version : ' . $clone->number . ' - not activated');
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true));
            return $this->getResponse()->setBody($jsonData);
        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    public function getErrorPageRespObjAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $response = $control->getResponse($activeVersion, Fastly_CDN_Model_Config::ERROR_PAGE_RESPONSE_OBJECT);

            if(!$response) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to fetch Error page Response object.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'errorPageResp' => $response));
            return $this->getResponse()->setBody($jsonData);
        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    public function updateErrorPageHtmlAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $html = $this->getRequest()->getParam('html');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $snippets = Mage::getModel('fastlycdn/config')->getVclSnippets(self::VCL_ERROR_SNIPPET_PATH, self::VCL_ERROR_SNIPPET);


            foreach($snippets as $key => $value)
            {
                $snippetData = array('name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_error_page_'.$key, 'type' => $key, 'dynamic' => "0", 'content' => $value);
                $status = $control->uploadSnippet($clone->number, $snippetData);

                if(!$status) {
                    $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to upload the Snippet file.'));
                    return $this->getResponse()->setBody($jsonData);
                }
            }

            $condition = array(
                'name' => Fastly_CDN_Model_Config::FASTLY_MAGENTO_MODULE.'_error_page_condition',
                'statement' => 'req.http.ResponseObject == "970"',
                'type' => 'REQUEST',
            );

            $createCondition = $control->createCondition($clone->number, $condition);

            if(!$createCondition) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create a RESPONSE condition.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $response = array(
                'name' => Fastly_CDN_Model_Config::ERROR_PAGE_RESPONSE_OBJECT,
                'request_condition' => $createCondition->name,
                'content'   =>  $html,
                'status' => "503"

            );

            $createResponse = $control->createResponse($clone->number, $response);

            if(!$createResponse) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create a RESPONSE object.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'active_version' => $clone->number));
            return $this->getResponse()->setBody($jsonData);
        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    public function getBackendsAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $activeVersion = $this->getRequest()->getParam('active_version');
            $backends = Mage::getModel('fastlycdn/control')->getBackends($activeVersion);

            if(!$backends) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Backend details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'backends' => $backends));
            return $this->getResponse()->setBody($jsonData);
        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    public function updateBackendAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activate_flag = $this->getRequest()->getParam('activate_flag');
            $activeVersion = $this->getRequest()->getParam('active_version');
            $oldName = $this->getRequest()->getParam('name');
            $params = [
                'name' => $this->getRequest()->getParam('name'),
                'shield' => $this->getRequest()->getParam('shield'),
                'connect_timeout' => $this->getRequest()->getParam('connect_timeout'),
                'between_bytes_timeout' => $this->getRequest()->getParam('between_bytes_timeout'),
                'first_byte_timeout' => $this->getRequest()->getParam('first_byte_timeout'),
            ];

            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $configureBackend = $control->configureBackend($params, $clone->number, $oldName);

            if(!$configureBackend) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to update Backend configuration.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activate_flag === 'true') {
                $control->activateVersion($clone->number);
                Mage::helper('fastlycdn/webhooks')->sendWebHook('Backend ' . $oldName . 'has been changed | Version : ' . $clone->number . ' - activated');
            } else {
                Mage::helper('fastlycdn/webhooks')->sendWebHook('Backend ' . $oldName . 'has been changed | Version : ' . $clone->number . ' - not activated');
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'active_version' => $clone->number, 'backends' => $configureBackend));
            return $this->getResponse()->setBody($jsonData);
        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Clones and activates new version (ACL)
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function createAclAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $aclName = $this->getRequest()->getParam('acl_name');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $status = $control->createAcl($clone->number, $aclName);

            if(!$status) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to create Acl.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(
                array(
                    'status' => true,
                    'active_version' => $clone->number,
                    'acl_name' => $status->name,
                    'acl_id' => $status->id)
            );
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Clones and activates new version (ACL)
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function deleteAclAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $activeVersion = $this->getRequest()->getParam('active_version');
            $activateVcl = $this->getRequest()->getParam('activate_flag');
            $aclName = $this->getRequest()->getParam('acl_name');
            $service = $control->checkServiceDetails();

            if(!$service) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to check Service details.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $currActiveVersion = Mage::helper('fastlycdn')->determineVersions($service->versions);

            if($currActiveVersion['active_version'] != $activeVersion) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Active versions mismatch.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $clone = $control->cloneVersion($currActiveVersion['active_version']);

            if(!$clone) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to clone active version.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $status = $control->deleteAcl($clone->number, $aclName);

            if(!$status) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to remove Acl.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $validate = $control->validateServiceVersion($clone->number);

            if($validate->status == 'error') {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to validate service version: '.$validate->msg));
                return $this->getResponse()->setBody($jsonData);
            }

            if($activateVcl === 'true') {
                $control->activateVersion($clone->number);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'active_version' => $clone->number));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Fetches Acl items
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function listAclItemsAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $aclId = $this->getRequest()->getParam('acl_id');

            if(!$aclId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing acl name.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $items = $control->getAclItems($aclId);

            if(is_array($items) && empty($items)) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => 'empty', 'msg' => 'This Acl has no items.'));
                return $this->getResponse()->setBody($jsonData);
            }

            if(!$items) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to fetch Acl items.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'items' => $items));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Add Acl item
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function addAclItemAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $aclId = $this->getRequest()->getParam('acl_id');
            $aclItemId = $this->getRequest()->getParam('acl_item_id');
            $ip = $this->getRequest()->getParam('ip');
            $negated = $this->getRequest()->getParam('negated');


            if(!$ip || !$negated) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Acl item data.'));
                return $this->getResponse()->setBody($jsonData);
            }

            if(!$aclId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Acl name.'));
                return $this->getResponse()->setBody($jsonData);
            }

            // Handle subnet
            $ipParts = explode('/', $ip);
            $subnet = false;
            if(!empty($ipParts[1])) {
                if(is_numeric($ipParts[1]) && (int)$ipParts[1] < 129) {
                    $subnet = $ipParts[1];
                } else {
                    $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Invalid IP subnet format.'));
                    return $this->getResponse()->setBody($jsonData);
                }
            }

            if (!filter_var($ipParts[0], FILTER_VALIDATE_IP)) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Invalid IP address format.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $item = $control->addAclItem($aclId, $aclItemId, $ipParts[0], $negated, $subnet);

            if(!$item) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to add Acl item.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true, 'item' => $item));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }

    /**
     * Remove Acl item
     *
     * @return Zend_Controller_Response_Abstract
     */
    public function removeAclItemAction()
    {
        try {
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $control = Mage::getModel('fastlycdn/control');

            $aclId = $this->getRequest()->getParam('acl_id');
            $aclItemId = $this->getRequest()->getParam('acl_item_id');

            if(!$aclItemId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Acl item data.'));
                return $this->getResponse()->setBody($jsonData);
            }

            if(!$aclId) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Missing Acl container ID.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $status = $control->removeAclItem($aclId, $aclItemId);

            if(!$status) {
                $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => 'Failed to remove Acl item.'));
                return $this->getResponse()->setBody($jsonData);
            }

            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => true));
            return $this->getResponse()->setBody($jsonData);

        } catch (\Exception $e) {
            $jsonData =  Mage::helper('core')->jsonEncode(array('status' => false, 'msg' => $e->getMessage()));
            return $this->getResponse()->setBody($jsonData);
        }
    }
}
