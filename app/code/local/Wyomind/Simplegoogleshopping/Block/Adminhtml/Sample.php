<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Block_Adminhtml_Sample extends Mage_Adminhtml_Block_Template
{

    public function _ToHtml()
    {
        $id = $this->getRequest()->getParam('simplegoogleshopping_id');


        $googleshopping = Mage::getModel('simplegoogleshopping/simplegoogleshopping');
        $googleshopping->setId($id);
        $googleshopping->limit = Mage::getStoreConfig("simplegoogleshopping/system/preview");
        $googleshopping->display = true;

        // if googleshopping record exists
        $googleshopping->load($id);

        try {
            $content = $googleshopping->generateXml();
            return "<textarea id='CodeMirror' class='CodeMirror'>" . $content . "</textarea>";
        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        } catch (Exception $e) {
            return Mage::helper('simplegoogleshopping')->__('Unable to generate the data feed.');
        }
    }

}
