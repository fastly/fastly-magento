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

class Fastly_CDN_Model_Observer
{
    const SET_CACHE_HEADER_FLAG = 'FASTLYCDN_CACHE_CONTROL_HEADERS_SET';

    /**
     * @var Fastly_CDN_Helper_Cache
     */
    protected $_cacheHelper = null;

    /**
     * @var Fastly_CDN_Helper_Tags
     */
    protected $_tagsHelper = null;


    /**
     * Retrieve session model
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    /**
     * Check if full page cache is enabled
     *
     * @return bool
     */
    protected function _isCacheEnabled()
    {
        return Mage::helper('fastlycdn')->isEnabled();
    }

    /**
     * Get fastlyCDN control model
     *
     * @return Fastly_CDN_Model_Control
     */
    protected function _getCacheControl()
    {
        return Mage::getSingleton('fastlycdn/control');
    }

    /**
     * Return cache helper
     *
     * @return Fastly_CDN_Helper_Cache
     */
    protected function _getCacheHelper()
    {
        if (is_null($this->_cacheHelper)) {
            $this->_cacheHelper = Mage::helper('fastlycdn/cache');
        }
        return $this->_cacheHelper;
    }

    /**
     * Return tags helper
     *
     * @return Fastly_CDN_Helper_Tags
     */
    protected function _getTagsHelper()
    {
        if (is_null($this->_tagsHelper)) {
            $this->_tagsHelper = Mage::helper('fastlycdn/tags');
        }
        return $this->_tagsHelper;
    }

    /**
     * Clean all Fastly CDN items
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function cleanCache(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $this->_getCacheControl()->cleanAll();

            $this->_getSession()->addSuccess(
                Mage::helper('fastlycdn')->__('The Fastly CDN has been cleaned.')
            );
        }
        return $this;
    }

    /**
     * Clean media (CSS/JS) cache
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function cleanMediaCache(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $control = $this->_getCacheControl();

            // clean css and js
            $control->cleanBySurrogateKey(Fastly_CDN_Model_Control::CONTENT_TYPE_CSS);
            $control->cleanBySurrogateKey(Fastly_CDN_Model_Control::CONTENT_TYPE_JS);

            // also clean HTML files
            $control->cleanBySurrogateKey(Fastly_CDN_Model_Control::CONTENT_TYPE_HTML);

            $this->_getSession()->addSuccess(
                Mage::helper('fastlycdn')->__('The JavaScript/CSS cache has been cleaned on the Varnish servers.')
            );
        }
        return $this;
    }

    /**
     * Clean catalog images cache
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function cleanCatalogImagesCache(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $control = $this->_getCacheControl();

            // clean images
            $control->cleanBySurrogateKey(Fastly_CDN_Model_Control::CONTENT_TYPE_IMAGE);

            // also clean HTML files
            $control->cleanBySurrogateKey(Fastly_CDN_Model_Control::CONTENT_TYPE_HTML);

            $this->_getSession()->addSuccess(
                Mage::helper('fastlycdn')->__('The catalog image cache has been cleaned on the Varnish servers.')
            );
        }
        return $this;
    }

    /**
     * Purge category
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function purgeCatalogCategory(Varien_Event_Observer $observer)
    {
        try {
            $category = $observer->getEvent()->getCategory();
            if (!Mage::registry('fastlycdn_catalog_category_purged_' . $category->getId())) {
                Mage::getModel('fastlycdn/control_catalog_category')->purge($category);
                Mage::register('fastlycdn_catalog_category_purged_' . $category->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Error on save category purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Purge product
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function purgeCatalogProduct(Varien_Event_Observer $observer)
    {
        try {
            $product = $observer->getEvent()->getProduct();
            if (!Mage::registry('fastlycdn_catalog_product_purged_' . $product->getId())) {
                Mage::getModel('fastlycdn/control_catalog_product')->purge($product, true, true);
                Mage::register('fastlycdn_catalog_product_purged_' . $product->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Error on save product purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Purge Cms Page
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function purgeCmsPage(Varien_Event_Observer $observer)
    {
        try {
            $page = $observer->getEvent()->getObject();
            if (!Mage::registry('fastlycdn_cms_page_purged_' . $page->getId())) {
                Mage::getModel('fastlycdn/control_cms_page')->purge($page);
                Mage::register('fastlycdn_cms_page_purged_' . $page->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Error on save cms page purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Purge product
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function purgeCatalogProductByStock(Varien_Event_Observer $observer)
    {
        try {
            $item = $observer->getEvent()->getItem();
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            if (!Mage::registry('fastlycdn_catalog_product_purged_' . $product->getId())) {
                Mage::getModel('fastlycdn/control_catalog_product')->purge($product, true, true);
                Mage::register('fastlycdn_catalog_product_purged_' . $product->getId(), true);
            }
        } catch (Exception $e) {
            Mage::helper('fastlycdn')->debug('Error on save product purging: '.$e->getMessage());
        }
        return $this;
    }

    /**
     * Set appropriate cache control headers
     *
     * @param Varien_Event_Observer $observer
     * @return Fastly_CDN_Model_Observer
     */
    public function setCacheControlHeaders(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            if (!Mage::registry(self::SET_CACHE_HEADER_FLAG)) {
                $this->_getCacheHelper()->setCacheControlHeaders();
                Mage::register(self::SET_CACHE_HEADER_FLAG, true);
            }
        }
        return $this;
    }

    /**
     * Disable page caching by setting no-cache header
     *
     * @param Varien_Event_Observer $observer | null
     * @return Fastly_CDN_Model_Observer
     */
    public function disablePageCaching($observer = null)
    {
        if ($this->_isCacheEnabled() || Mage::app()->getStore()->isAdmin()) {
            $this->_getCacheHelper()->setNoCacheHeader();
        }
        return $this;
    }

    /**
     * Sets shutdown listener to ensure cache control headers sent in case script exits unexpectedly
     *
     * @return Fastly_CDN_Model_Observer
     */
    public function registerShutdownFunction()
    {
        if ($this->_isCacheEnabled()) {
            /**
             *  workaround for PHP bug with autoload and open_basedir restriction:
             *  ensure the Zend exception class is loaded.
             */
            $exception = new Zend_Controller_Response_Exception;
            unset($exception);

            // register shutdown method
            register_shutdown_function(array($this->_getCacheHelper(), 'setCacheControlHeadersRaw'));
        }
        return $this;
    }

    /**
     * Set no-cache cookie for messages if messages have not been cleared before shutdown.
     *
     * @param Varien_Event_Observer $observer
     */
    public function handleMessageCookie(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            /**
             * the following lines are not "lege artis" but very efficient if assumed that the session structure for
             * messages are designed in "Magento style". that is each session object has its namespace in $_SESSION with
             * the property "messages" which is an instance of Mage_Core_Model_Message_Collection that holds all
             * messages for the session object. If the count of message items is > 0 before shutdown a message-no-cache
             * cookie should be set to keep fastlyCDN from serving cached pages until the messages have been cleared
             * again. if no messages are in the session objects but a message-no-cache cookie has been send in the
             * request the messages have been cleared now and the message-no-cache cookie can be removed again, to allow
             * fastlyCDN to serve cached pages for the next request.
             */
            $cntMessagesInSession = 0;
            if (isset($_SESSION)) {
                foreach ($_SESSION as $sessionData) {
                    if (isset($sessionData['messages']) &&
                        $sessionData['messages'] instanceof Mage_Core_Model_Message_Collection) {
                        $cntMessagesInSession += $sessionData['messages']->count();
                    }
                }
            }

            if ($cntMessagesInSession > 0) {
                $this->_getCacheHelper()->setNoCacheHeader();
            }
        }
    }

    /**
     * replace all occurrences of the form key
     *
     * @param Varien_Event_Observer $observer
     * @return false | void
     */
    public function replaceFormKeys(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            $esiHelper = Mage::helper('fastlycdn/esi');
            /* @var $esiHelper Fastly_CDN_Helper_Esi */
            if (!$esiHelper->hasFormKey() || Mage::app()->getRequest()->isPost()) {
                return false;
            }

            $response = $observer->getResponse();
            $html     = $response->getBody();
            $html     = $esiHelper->replaceFormKey($html);

            $response->setBody($html);
        }
    }

    /**
     * Register form key in session from cookie value
     *
     * @param Varien_Event_Observer $observer
     */
    public function registerCookieFormKey(Varien_Event_Observer $observer)
    {
        if ($this->_isCacheEnabled()) {
            if ($formKey = Mage::helper('fastlycdn/esi')->getCookieFormKey()) {
                $session = Mage::getSingleton('core/session');
                $session->setData('_form_key', $formKey);
            }
        }
    }

    /**
     * set the environment cookie after currency switch
     */
    public function setEnvironmentCookie()
    {
        Mage::helper('fastlycdn/environment')->setEnvironmentCookie();
    }

    /**
     * @return Fastly_CDN_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('fastlycdn');
    }

    /**
     * @return Mage_Core_Model_Cookie
     */
    protected function _getCookie()
    {
        return Mage::getSingleton('core/cookie');
    }

    /**
     * Check if ESI functionality is enabled
     *
     * @return bool
     */
    protected function _isEsiEnabled()
    {
        return $this->_getHelper()->isEsiEnabled();
    }

    /**
     * Check if ESI functionality can be used
     *
     * @return bool
     */
    protected function _canUseEsi()
    {
        return $this->_getHelper()->canUseEsi();
    }


    /**
     * Replace block HTML by ESI include tag
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function replaceBlockByEsiTag(Varien_Event_Observer $observer)
    {
        // if ESI functionality is disabled
        // or ESI block is currently rendering
        // or this is a POST request
        // => stop processing
        if (!$this->_canUseEsi() || Mage::registry('ESI_PROCESSING') == true) {
            return $this;
        }

        $block     = $observer->getEvent()->getBlock();
        $transport = $observer->getEvent()->getTransport();
        $esiTag    = $this->_getHelper()->getEsiTagModelByBlock($block);

        if ($transport && $esiTag) {
            $blockHtml = '';
            if ($this->_getHelper()->isEsiDebugEnabled()) {
                // keep original block content and wrap it with <esi:remove> tag
                $blockHtml .= $esiTag->getStartRemoveTag()
                    . $transport->getHtml()
                    . $esiTag->getEndRemoveTag();
            }
            $blockHtml .= $esiTag->getEsiIncludeTag($block);
            $transport->setHtml($blockHtml);
        }
    }

    /**
     * Set cookies for customer and quote.
     *
     * This is required by ESI blocks like header (customer name, cart total) and sidebar cart.
     *
     * @param Varien_Event_Observer $observer
     */
    public function updateCustomBlocks(Varien_Event_Observer $observer)
    {
        $this->_setSidebarCartCookie();
        $this->_setPageHeaderCookie();
    }

    /**
     * Set cookie for compare list.
     *
     * This is required by the ESI block catalog_product_compare_sidebar.
     *
     * @param Varien_Event_Observer $observer
     */
    public function updateCompareBlock(Varien_Event_Observer $observer)
    {
        $this->_setCompareListCookie();
    }

    /**
     * Set cookie for compare list.
     *
     * This is required by the ESI block catalog_product_compare_sidebar.
     *
     * @param Varien_Event_Observer $observer
     */
    public function updateCompareViewedBlock(Varien_Event_Observer $observer)
    {
        $this->_setRecentlyComparedCookie();
    }

    /**
     * Set cookie for wishlist.
     *
     * This is required by the ESI block wishlist_customer_sidebar.
     *
     * @param Varien_Event_Observer $observer
     */
    public function updateWishlistBlock(Varien_Event_Observer $observer)
    {
        $this->_setWishlistCookie();
        $this->_setPageHeaderCookie();
    }

    /**
     * remove compare list cookie
     *
     * This is required by the ESI block catalog_product_compare_sidebar.
     *
     * @param Varien_Event_Observer $observer
     */
    public function disableCompareBlock(Varien_Event_Observer $observer)
    {
        if ($this->_canUseEsi()) {
            $this->_getCookie()->delete(
                $this->_getHelper()->generateCookieName(
                    Fastly_CDN_Model_Esi_Tag_Catalog_product_Compare_Sidebar::COOKIE_NAME
                )
            );
        }
    }

    /**
     * Remove page header cookies on customer logout
     *
     * @param Varien_Event_Observer $observer
     */
    public function disableCustomBlocks(Varien_Event_Observer $observer)
    {
        if ($this->_canUseEsi()) {
            $this->_getCookie()->delete(
                $this->_getHelper()->generateCookieName(
                    Fastly_CDN_Model_Esi_Tag_Checkout_Sidebar_Cart::COOKIE_NAME
                )
            );
            $this->_getCookie()->delete(
                $this->_getHelper()->generateCookieName(
                    Fastly_CDN_Model_Esi_Tag_Page_Template_Links::COOKIE_NAME
                )
            );
        }
    }

    /**
     * Delete URL encoded referer to heal links in ESI blocks.
     *
     * @param Varien_Event_Observer $observer
     */
    public function removeUencReferer(Varien_Event_Observer $observer)
    {
        $action = $observer->getEvent()->getControllerAction();
        /* @var $action Mage_Core_Controller_Varien_Action */
        $action->getRequest()->setParam(
            Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED,
            null
        );
    }

    /**
     * extract cache tags from blocks
     *
     * @param Varien_Event_Observer $observer
     */
    public function inspectBlocks(Varien_Event_Observer $observer)
    {
        $helper = $this->_getTagsHelper();

        // get blocks cache tags
        $block     = $observer->getEvent()->getBlock();
        $cacheTags = $block->getCacheTags();

        // cache tags to look up
        $cacheTagLUT = array(
            Mage_Catalog_Model_Product::CACHE_TAG,
            Mage_Catalog_Model_Category::CACHE_TAG,
            Mage_Cms_Model_Page::CACHE_TAG,
            Mage_Cms_Model_Block::CACHE_TAG,
        );

        // extract cache tags for models
        foreach ($cacheTagLUT as $cacheTag) {
            $ids = $helper->extractTagIds($cacheTags, $cacheTag);
            if (count($ids)) {
                Fastly_CDN_Model_Tag::addTagIds($cacheTag, $ids);
            }
        }
    }

    /**
     * set surrogate key header
     *
     * @param Varien_Event_Observer $observer
     */
    public function setTagHeaders(Varien_Event_Observer $observer)
    {
        $response = $observer->getResponse();

        // get ids from container
        $cacheTagLUT = array(
            Mage_Catalog_Model_Product::CACHE_TAG  => Fastly_CDN_Helper_Tags::SURROGATE_KEY_PRODUCT_PREFIX,
            Mage_Catalog_Model_Category::CACHE_TAG => Fastly_CDN_Helper_Tags::SURROGATE_KEY_CATEGORY_PREFIX,
            Mage_Cms_Model_Page::CACHE_TAG         => Fastly_CDN_Helper_Tags::SURROGATE_KEY_CMSPAGE_PREFIX,
            Mage_Cms_Model_Block::CACHE_TAG        => Fastly_CDN_Helper_Tags::SURROGATE_KEY_CMSBLOCK_PREFIX,
        );

        // build surrogate key for every cache tag type
        $surrogateKeys = array();
        foreach ($cacheTagLUT as $cacheTag => $prefix) {
            $ids = Fastly_CDN_Model_Tag::getTagsIds($cacheTag);
            $tagContent = preg_filter('/^/', $prefix, $ids);
            $surrogateKey = implode(' ', $tagContent);
            if ($surrogateKey != '') {
                $surrogateKeys[] = $surrogateKey;
            }
        }

        // add current store
        $surrogateKeys[] = Fastly_CDN_Helper_Tags::SURROGATE_KEY_STORE_PREFIX . Mage::app()->getStore()->getStoreId();

        // add surrogate keys to header
        if (count($surrogateKeys)) {
            $response->setHeader(
                Fastly_CDN_Helper_Tags::SURROGATE_KEY_HEADER_NAME,
                implode(' ', $surrogateKeys),
                true
            );
        }
    }

    /**
     * sets the page header cookies
     */
    protected function _setPageHeaderCookie() {
        // page header depends on customer id and quote id
        $cookieData = array(
            'customerId' => Mage::getSingleton('customer/session')->getCustomerId(),
            'quoteId'    => Mage::getSingleton('checkout/session')->getQuote()->getId()
        );

        $this->_getHelper()->setEsiCookie(
            $cookieData,
            true,
            Fastly_CDN_Model_Esi_Tag_Page_Template_Links::COOKIE_NAME
        );
    }

    /**
     * sets the page header cookie
     */
    protected function _setSidebarCartCookie() {
        // sidebar cart depends on quote id
        $cookieData = array(
            'quoteId' => Mage::getSingleton('checkout/session')->getQuote()->getId()
        );

        $this->_getHelper()->setEsiCookie(
            $cookieData,
            true,
            Fastly_CDN_Model_Esi_Tag_Checkout_Sidebar_Cart::COOKIE_NAME
        );
    }

    /**
     * sets the page header cookie
     */
    protected function _setWishlistCookie() {
        // wishlist depends on item ids in list
        $itemIds = array();

        foreach (Mage::getModel('wishlist/wishlist')->getItemCollection() as $item) {
            $itemIds[] = $item->getId();
        }

        $cookieData = array(
            'list' => implode(',', $itemIds)
        );

        $this->_getHelper()->setEsiCookie(
            $cookieData,
            true,
            Fastly_CDN_Model_Esi_Tag_Wishlist_Customer_Sidebar::COOKIE_NAME
        );
    }

    /**
     * sets the compare list cookie
     */
    protected function _setCompareListCookie() {
        // compare list depends on item ids in list
        $itemIds = array();
        foreach (Mage::helper('catalog/product_compare')->getItemCollection() as $item) {
            $itemIds[] = $item->getId();
        }

        $cookieData = array(
            'list' => implode(',', $itemIds)
        );

        $this->_getHelper()->setEsiCookie(
            $cookieData,
            true,
            Fastly_CDN_Model_Esi_Tag_Catalog_product_Compare_Sidebar::COOKIE_NAME
        );

        // also update recently compared cookie
        $this->_setRecentlyComparedCookie();
    }

    /**
     * sets the recently compared list cookie
     */
    protected function _setRecentlyComparedCookie() {
        // get previous compare list from cookie
        $itemsInPreviousCompareList = $this->_getHelper()->getEsiCookieData(
            Fastly_CDN_Model_Esi_Tag_Catalog_product_Compare_Sidebar::COOKIE_NAME,
            'list'
        );
        $itemsInPreviousCompareList = (empty($itemsInPreviousCompareList)) ? array()
            : explode(',', $itemsInPreviousCompareList);


        // store current compare lists items in cookie
        $itemsInCompareList = Mage::helper('catalog/product_compare')->getItemCollection();

        $compareListIds = array();
        foreach ($itemsInCompareList as $item) {
            $compareListIds[] = $item->getId();
        }
        sort($compareListIds);

        $cookieData = array(
            'list' => implode(',', $compareListIds)
        );
        $this->_getHelper()->setEsiCookie(
            $cookieData,
            true,
            Fastly_CDN_Model_Esi_Tag_Catalog_product_Compare_Sidebar::COOKIE_NAME
        );

        // get recently compared product from cookie
        $recentlyComparedProducts = $this->_getHelper()->getEsiCookieData(
            Fastly_CDN_Model_Esi_Tag_Reports_Product_Compared::COOKIE_NAME,
            'list'
        );
        $recentlyComparedProducts = (empty($recentlyComparedProducts)) ? array()
            : explode(',', $recentlyComparedProducts);

        // build new recently compared list
        $deletedProducts = array_diff($itemsInPreviousCompareList, $compareListIds);
        $addedProducts   = array_diff($compareListIds, $itemsInPreviousCompareList);

        $recentlyComparedProducts = array_merge($recentlyComparedProducts, $deletedProducts);
        $recentlyComparedProducts = array_diff($recentlyComparedProducts, $addedProducts);
        $recentlyComparedProducts = array_unique($recentlyComparedProducts);
        sort($recentlyComparedProducts);

        // store recently compared list
        $cookieData = array(
            'list' => implode(',', $recentlyComparedProducts)
        );

        $this->_getHelper()->setEsiCookie(
            $cookieData,
            true,
            Fastly_CDN_Model_Esi_Tag_Reports_Product_Compared::COOKIE_NAME
        );
    }

    /**
     * Sends installed request to GA
     */
    public function installedEvent()
    {
        if (Mage::helper('core')->isModuleEnabled('Fastly_CDN')) {

            $resource = Mage::getSingleton('core/resource');
            $statistic = Mage::getModel('fastlycdn/statistic');
            $tableName = $resource->getTableName('fastlycdn/statistics');

            if($resource->getConnection('core_read')->isTableExists($tableName)) {
                $stat = $statistic->getCollection()->getStatByAction($statistic::FASTLY_INSTALLED_FLAG);

                if($stat->getSent() == false) {
                    $sendGAReq = $statistic->sendInstalledReq();
                    if($sendGAReq) {
                        $statistic->load($stat->getId());
                        $statistic->setState(false);
                        $statistic->setAction($statistic::FASTLY_INSTALLED_FLAG);
                        $statistic->setSent($sendGAReq);
                        $statistic->save();
                    }
                }

                $fastlyVer = Mage::helper('fastlycdn')->__(Mage::getConfig()->getNode('modules/Fastly_CDN/version'));

                if(isset($fastlyVer)) {
                    if ($fastlyVer > trim(Mage::getStoreConfig(Fastly_CDN_Helper_Data::XML_FASTLY_MODULE_VERSION))) {
                        $statistic->sendUpgradeRequest();
                        Mage::getConfig()->saveConfig(Fastly_CDN_Helper_Data::XML_FASTLY_MODULE_VERSION, $fastlyVer);
                        Mage::app()->getCacheInstance()->cleanType('config');
                    }
                }
            }
        }
    }

    /**
     * Sends configuration request to GA
     */
    public function configurationEvent(Varien_Event_Observer $observer)
    {
        if (Mage::helper('core')->isModuleEnabled('Fastly_CDN')) {
            $statistic = Mage::getModel('fastlycdn/statistic');
            $isServiceValid = $statistic->isApiKeyValid();
            $stat = $statistic->getCollection()->getStatByAction($statistic::FASTLY_CONFIGURATION_FLAG);

            if((!$stat->getId()) || !($stat->getState() == true && $isServiceValid == true) ) {
                $GAreq = $statistic->sendConfigurationRequest($isServiceValid);

                $statistic->setAction($statistic::FASTLY_CONFIGURATION_FLAG);
                $statistic->setState($isServiceValid);
                $statistic->setSent($GAreq);
                $statistic->save();
            }
        }
    }
}
