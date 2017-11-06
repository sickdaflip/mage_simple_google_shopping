<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_SimplegoogleshoppingController extends Mage_Core_Controller_Front_Action
{
    public function generateAction()
    {
        // http://www.example.com/index.php/simplegoogleshopping/simplegoogleshopping/generate/id/{data_feed_id}/ak/{YOUR_ACTIVATION_KEY}

        $id = $this->getRequest()->getParam('id');
        $ak = $this->getRequest()->getParam('ak');

        $activationKey = Mage::getStoreConfig("simplegoogleshopping/license/activation_key");

        if ($activationKey == $ak) {
            $simplegoogleshopping = Mage::getModel('simplegoogleshopping/simplegoogleshopping');
            $simplegoogleshopping->setId($id);
            if ($simplegoogleshopping->load($id)) {
                try {
                    $simplegoogleshopping->generateXml();
                    $this->getResponse()->setBody(
                        Mage::helper('simplegoogleshopping')->__(
                            'The data feed "%s" has been generated.', 
                            $simplegoogleshopping->getSimplegoogleshoppingFilename()
                        )
                    );
                } catch (Mage_Core_Exception $e) {
                    $this->getResponse()->setBody($e->getMessage());
                } catch (Exception $e) {
                    $this->getResponse()->setBody($e->getMessage());
                }
            } else {
                $this->getResponse()->setBody('Unable to find a data feed to generate.');
            }
        } else {
            $this->getResponse()->setBody('Invalid activation key');
        }
    }
}
