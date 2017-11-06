<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
$installer = $this;

$installer->startSetup();

$installer->getConnection()->addColumn(
    $installer->getTable('simplegoogleshopping'), 'simplegoogleshopping_attribute_sets', array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'length'    => 150,
        'nullable'  => true,
        'default'   => '*',
        'comment'   => 'Attribute sets'
    )
);


$installer->endSetup();
