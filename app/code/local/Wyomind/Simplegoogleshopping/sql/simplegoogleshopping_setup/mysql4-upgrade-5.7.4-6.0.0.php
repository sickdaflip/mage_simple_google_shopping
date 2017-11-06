<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
$installer = $this;

$installer->startSetup();

$installer->run(
    "ALTER TABLE {$this->getTable('simplegoogleshopping')} MODIFY `cron_expr` varchar(900) NOT NULL "
        . "DEFAULT '{\"days\":[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\",\"Sunday\"],"
        . "\"hours\":[\"00:00\",\"04:00\",\"08:00\",\"12:00\",\"16:00\",\"20:00\"]}';"
);

//BLOB/TEXT column 'cron_expr' can't have a default value
//$installer->getConnection()->modifyColumn(
//    $installer->getTable('simplegoogleshopping'), 'cron_expr', array(
//        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
//        'length'    => 900,
//        'nullable'  => false,
//        'default'   => '{\"days\":[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\",\"Sunday\"],\"hours\":[\"00:00\",\"04:00\",\"08:00\",\"12:00\",\"16:00\",\"20:00\"]}'
//    )
//);

$installer->getConnection()->modifyColumn(
    $installer->getTable('simplegoogleshopping'), 'simplegoogleshopping_time', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
        'nullable'  => false,
        'default'   => Varien_Db_Ddl_Table::TIMESTAMP_INIT
    )
);

$installer->getConnection()->addColumn(
    $installer->getTable('simplegoogleshopping'), 'simplegoogleshopping_category_filter', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'nullable'  => true,
        'default'   => 1,
        'length'    => 1,
        'comment'   => 'Category filter'
    )
);

$installer->endSetup();

