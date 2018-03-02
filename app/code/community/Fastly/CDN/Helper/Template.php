<?php

class Fastly_CDN_Helper_Template extends Mage_Core_Helper_Abstract
{

    const FASTLY_CDN_TEMPLATE_QUERY_PARAMS = 'fastlycdn/general/template_query';

    /**
     * Replace snippet template constants with variables
     * @param $value
     * @return mixed
     */
    public function replaceSnippetValues($value)
    {
        // Replace Admin path
        $adminPath = Mage::getConfig()->getNode('admin/routers/adminhtml/args/frontName')->asArray();
        if(!$adminPath || !is_string($adminPath)) {
            $adminPath = 'admin';
        }

        // Ignore Query params
        $queryParams = Mage::getStoreConfig(self::FASTLY_CDN_TEMPLATE_QUERY_PARAMS);
        $queryParams = explode(',', $queryParams);
        $queryParams = array_filter(array_map('trim', $queryParams));
        $queryParams = implode('|', $queryParams);

        $replaceValues = array(
            '####ADMIN_PATH####' => $adminPath,
            '####QUERY_PARAMETERS####' => $queryParams//'utm_.*|gclid|gdftrk|_ga|mc_.*'
        );

        foreach($replaceValues as $search => $replace) {
            $value = str_replace($search, $replace, $value);
        }

        return $value;
    }
}