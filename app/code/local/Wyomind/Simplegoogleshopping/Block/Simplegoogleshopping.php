<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Block_Simplegoogleshopping extends Mage_Core_Block_Template
{
    public function getSimplegoogleshopping()
    {
        if (!$this->hasData('simplegoogleshopping')) {
            $this->setData('simplegoogleshopping', Mage::registry('simplegoogleshopping'));
        }
        return $this->getData('simplegoogleshopping');
    }

}
