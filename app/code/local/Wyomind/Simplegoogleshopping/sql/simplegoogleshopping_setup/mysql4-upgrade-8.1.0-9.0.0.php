<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('simplegoogleshopping'), 'simplegoogleshopping_category_type', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
        'length'    => 1,
        'nullable'  => true,
        'default'   => 0,
        'comment'   => 'Category type'
    )
);

$installer->getConnection()->addColumn(
    $installer->getTable('simplegoogleshopping'), 'simplegoogleshopping_report', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => true,
        'comment'   => 'Report'
    )
);

$installer->endSetup();