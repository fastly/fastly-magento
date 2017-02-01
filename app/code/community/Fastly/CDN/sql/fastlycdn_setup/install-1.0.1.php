<?php

/**
 * Fastly Statistic install
 */
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */
$connection = $installer->getConnection();

$installer->startSetup();

/**
 * Create table 'fastly_statistics'
 */

$table = $connection->newTable($installer->getTable('fastlycdn/statistics')
)->addColumn(
    'stat_id',
    Varien_Db_Ddl_Table::TYPE_INTEGER,
    null,
    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
    'Stat id'
)->addColumn(
    'action',
    Varien_Db_Ddl_Table::TYPE_TEXT,
    30,
    ['nullable' => false],
    'Fastly action'
)->addColumn(
    'sent',
    Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    null,
    ['nullable' => false, 'default' => 0],
    '1 = Curl req. sent | 0 = Curl req. not sent'
)->addColumn(
    'state',
    Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    null,
    ['nullable' => false, 'default' => 0],
    '1 = configured | 0 = not_configured'
)->addColumn(
    'created_at',
    Varien_Db_Ddl_Table::TYPE_DATETIME,
    null,
    [],
    'Action date'
);
$connection->createTable($table);

/**
 * Insert Installed action into the statistic table
 */

$tableName = $installer->getTable('fastly_statistics');

if($connection->isTableExists($tableName) == true) {

    $statistic = Mage::getModel('fastlycdn/statistic');
    $cid = $statistic->generateCid();
    Mage::getConfig()->saveConfig('system/full_page_cache/fastly/fastly_ga_cid', $cid);

    $sendInstalledReq = $statistic->sendInstalledReq();

    $installedData = array(
        'action' => $statistic::FASTLY_INSTALLED_FLAG,
        'created_at' => Varien_Date::now(),
        'sent'  => $sendInstalledReq
    );

    $statistic->setData($installedData);
    $statistic->save();
}

$installer->endSetup();