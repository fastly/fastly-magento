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

class Fastly_CDN_Controller_Router extends Mage_Core_Controller_Varien_Router_Standard
{
    /**
     * Initialize Controller Router
     *
     * @param Varien_Event_Observer $observer
     */
    public function initControllerRouters($observer)
    {
        $front = $observer->getEvent()->getFront();
        /* @var $front Mage_Core_Controller_Varien_Front */
        $front->addRouter('fastlyCDN', $this);
    }

    /**
     * Validate and Match Cms Page and modify request
     *
     * @param Zend_Controller_Request_Http $request
     * @return bool
     */
    public function match(Zend_Controller_Request_Http $request)
    {
        $front = $this->getFront();
        $path = trim($request->getPathInfo(), '/');

        if ($path) {
            $p = explode('/', $path);
        }

        // get module name
        if ($request->getModuleName()) {
            $module = $request->getModuleName();
        } else {
            if (!empty($p[0])) {
                $module = $p[0];
            }
        }

        if ($module != 'fastlycdn') {
            return false;
        }

        // we only handle Fastly CDN requests
        $realModule = 'Fastly_CDN';

        $request->setRouteName($this->getRouteByFrontName($module));

        // get controller name
        if ($request->getControllerName()) {
            $controller = $request->getControllerName();
        } else {
            if (!empty($p[1])) {
                $controller = $p[1];
            } else {
                $controller = $front->getDefault('controller');
                $request->setAlias(
                    Mage_Core_Model_Url_Rewrite::REWRITE_REQUEST_PATH_ALIAS,
                    ltrim($request->getOriginalPathInfo(), '/')
                );
            }
        }

        // get action name
        if (empty($action)) {
            if ($request->getActionName()) {
                $action = $request->getActionName();
            } else {
                $action = !empty($p[2]) ? $p[2] : $front->getDefault('action');
            }
        }

        $controllerClassName = $this->_validateControllerClassName($realModule, $controller);
        if (!$controllerClassName) {
            return false;
        }

        // instantiate controller class
        $controllerInstance = Mage::getControllerInstance($controllerClassName, $request, $front->getResponse());

        if (!$controllerInstance->hasAction($action)) {
            $action = 'processor';
        }

        // set values only after all the checks are done
        $request->setModuleName($module);
        $request->setControllerName($controller);
        $request->setActionName($action);
        $request->setControllerModule($realModule);

        // set parameters from pathinfo
        for ($i = 3, $l = sizeof($p); $i < $l; $i += 2) {
            $request->setParam($p[$i], isset($p[$i+1]) ? urldecode($p[$i+1]) : '');
        }

        // dispatch action
        $request->setDispatched(true);
        $controllerInstance->dispatch($action);

        return true;
    }
}