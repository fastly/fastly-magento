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
 * create or update a cms block
 *
 * @param array $blocks
 */
function saveCmsBlocks(array $blocks)
{
    foreach ($blocks as $data) {
        $found   = false;
        $_blocks = Mage::getModel('cms/block')->getCollection()->addFieldToFilter('identifier', $data['identifier']);

        foreach($_blocks as $_block) {
            $block = Mage::getModel('cms/block')->load($_block->getId());
            if (count(array_intersect($block->getStores(), $data['stores'])) == count($block->getStores())) {
                $found = true;

                foreach ($data as $key => $value) {
                    $block->setData($key, $value);
                }
                $block->save();

                break;
            }
        }

        if ($found == false) {
            Mage::getModel('cms/block')->setData($data)->save();
        }
    }
}

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$cmsBlockGeoIp_EN = '
<script type="text/javascript">// <![CDATA[
function switchStore() {
    var url = $$("#geoip-select option").find(function(ele){return !!ele.selected}).value;
    if(url) {window.location.href=url;}
}
// ]]></script>
<div style="padding: 10px; position: relative;">
<a style="position: absolute; top: 5px; right: 5px; text-decoration: none; color: #000; font-size: 16px;" href="javascript:Dialog.closeInfo()">✖</a>
<h1 style="font-size: 18pt; color: #ccc;">Notice</h1>
<p style="font-size: 12pt;">This shop will not sell to your country.<br /> Please visit one of our other online shops.</p>
<select id="geoip-select"><option value="">please select country</option><option value="DE">Deutschland</option></select>
<button style="display: block; background: #000; color: #fff; border: none; padding: 5px 10px; margin: 10px 0; text-decoration: none; text-align: center;" onclick="switchStore()">load</button>
</div>
';

$cmsBlockGeoIp_DE = '
<script type="text/javascript">// <![CDATA[
function switchStore() {
    var url = $$("#geoip-select option").find(function(ele){return !!ele.selected}).value;
    if(url) {window.location.href=url;}
}
// ]]></script>
<div style="padding: 10px; position: relative;">
<a style="position: absolute; top: 5px; right: 5px; text-decoration: none; color: #000; font-size: 16px;" href="javascript:Dialog.closeInfo()">✖</a>
<h1 style="font-size: 18pt; color: #ccc;">Notice</h1>
<p style="font-size: 12pt;">Dieser Shop liefert nicht in Ihr Land.<br /> Bitte besuchen Sie einen unserer anderen Online-Shops.</p>
<select id="geoip-select"><option value="">please select country</option><option value="EN">United Kingdom</option></select>
<button style="display: block; background: #000; color: #fff; border: none; padding: 5px 10px; margin: 10px 0; text-decoration: none; text-align: center;" onclick="switchStore()">weiter</button>
</div>
';

/**
 * add CMS blocks for GeoIP modal windows
 */
saveCmsBlocks(array(
    array(
        'title'         => 'Fastly CDN GeoIp dialog in English',
        'identifier'    => 'fastly_cdn_geoip_dialog_EN',
        'content'       => $cmsBlockGeoIp_EN,
        'is_active'     => 1,
        'stores'        => array(0)
    ),
    array(
        'title'         => 'Fastly CDN GeoIp dialog in German',
        'identifier'    => 'fastly_cdn_geoip_dialog_DE',
        'content'       => $cmsBlockGeoIp_DE,
        'is_active'     => 1,
        'stores'        => array(0)
    ),
));


$installer->endSetup();
