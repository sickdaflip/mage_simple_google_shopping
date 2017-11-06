<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Adminhtml_SimplegoogleshoppingController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        Mage::helper('simplegoogleshopping')->checkHeartbeat();

        // check configuration
        if (Mage::getStoreConfig('system/cron/schedule_generate_every') > 
                Mage::getStoreConfig('system/cron/schedule_ahead_for')) {
            $this->_getSession()->addError(
                $this->__(
                    'Configuration problem. "Generate Schedules Every" is higher than '
                    . '"Schedule Ahead for". Please check your <a href="%s">configuration settings</a>.', 
                    $this->getUrl('adminhtml/system_config/edit', array('section' => 'system')) . '#system_cron'
                )
            );
        }
        $this->loadLayout();
        $this->_title($this->__('Simple Google Shopping'));
        $this->_setActiveMenu('catalog/googleshopping');
        $this->renderLayout();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/simplegoogleshopping');
    }

    public function editAction()
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('simplegoogleshopping/simplegoogleshopping')->load($id);


        if ($model->getId() || $id == 0) {
            $data = $this->_getSession()->getFormData(true);
            if (!empty($data)) {
                $model->setData($data);
            }

            Mage::register('simplegoogleshopping_data', $model);

            $this->loadLayout();
            $this->_title($this->__('Simple Google Shopping'));
            $this->_setActiveMenu('catalog/googleshopping');

            $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);
            $this->_addContent(
                $this->getLayout()
                        ->createBlock('simplegoogleshopping/adminhtml_simplegoogleshopping_edit')
            )
                    ->_addLeft(
                        $this->getLayout()
                                ->createBlock('simplegoogleshopping/adminhtml_simplegoogleshopping_edit_tabs')
                    );

            $this->renderLayout();
        } else {
            $this->_getSession()->addError(Mage::helper('simplegoogleshopping')->__('Item does not exist'));
            $this->_redirect('*/*/');
        }
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    public function generateAction()
    {
        try {
            $id = $this->getRequest()->getParam('simplegoogleshopping_id');
            $googleshopping = Mage::getModel('simplegoogleshopping/simplegoogleshopping');
            $googleshopping->setId($id);

            if ($googleshopping->load($id)) {
                $googleshopping->generateXml();
                $this->generatePromotionDatafeed($googleshopping);

                $report = Mage::helper("simplegoogleshopping")->generationStats($googleshopping);

                Mage::getSingleton('core/session')->addSuccess(
                    Mage::helper('simplegoogleshopping')
                        ->__(
                            'The data feed "%s" has been generated.', 
                            $googleshopping->getSimplegoogleshoppingFilename()
                        ) . "<br>" . $report
                );
            } else {
                Mage::throwException(
                    Mage::helper('simplegoogleshopping')
                    ->__('Unable to find a data feed to generate.')
                );
            }
            if ($this->getRequest()->getParam('generate')) {
                $this->_forward('edit', null, null, array("id" => $id));
            } else {
                $this->_forward('index');
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_forward('index');
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_getSession()->addException(
                $e, Mage::helper('simplegoogleshopping')->__('Unable to generate the data feed.') . $e->getMessage()
            );
            $this->_forward('index');
        }
    }
    
    /**
     * Use Google Merchant Promotion
     * @param Wyomind_Simplegoogleshopping_Model_Simplegoogleshopping $googleshopping
     */
    protected function generatePromotionDatafeed($googleshopping)
    {
        if (Mage::helper("core")->isModuleEnabled("Wyomind_Googlemerchantpromotions")) {
            if ($googleshopping->getSimplegoogleshoppingPromotions()) {
                $promoFileName = Mage::getStoreConfig('googlemerchantpromotions/settings/prefix') 
                        . str_replace(".xml", "", $googleshopping->getSimplegoogleshoppingFilename()) 
                        . Mage::getStoreConfig('googlemerchantpromotions/settings/suffix') . ".xml";
                Mage::helper("googlemerchantpromotions")->generateDatafeed($promoFileName, $googleshopping);
                
                Mage::getSingleton('core/session')->addSuccess(
                    Mage::helper('simplegoogleshopping')
                    ->__('The data feed "%s" has been generated.', $promoFileName) . "<br>"
                );
            }
        }
    }

    public function sampleAction()
    {
        try {
            $this->loadLayout()->renderLayout();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_forward('index');
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_getSession()->addException(
                $e, Mage::helper('simplegoogleshopping')->__('Unable to generate the data feed.')
            );
            $this->_forward('index');
        }
    }

    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        
        if ($data) {
            $model = Mage::getModel('simplegoogleshopping/simplegoogleshopping');
            if ($this->getRequest()->getParam('simplegoogleshopping_id')) {
                $model->load($this->getRequest()->getParam('simplegoogleshopping_id'));
            }

            $model->setData($data);
            try {
                $model->save();
                $this->_getSession()->addSuccess(
                    Mage::helper('simplegoogleshopping')->__('The data feed has been saved.')
                );
                $this->_getSession()->setFormData(false);

                if ($this->getRequest()->getParam('continue')) {
                    $this->getRequest()->setParam('id', $model->getId());
                    $this->_forward('edit');
                    return;
                }
                if ($this->getRequest()->getParam('generate')) {
                    $this->getRequest()->setParam('simplegoogleshopping_id', $model->getId());
                    $this->_forward('generate');
                    return;
                }
                $this->_forward('index');
                return;
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_getSession()->setFormData($data);
                $this->_redirect(
                    '*/*/edit', 
                    array('simplegoogleshopping_id' => $this->getRequest()->getParam('simplegoogleshopping_id'))
                );
                return;
            }
        }
        $this->_forward('index');
    }

    public function deleteAction()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $model = Mage::getModel('simplegoogleshopping/simplegoogleshopping');
                $model->setId($id);
                $model->load($id);
                
                $io = new Varien_Io_File();
                $filePath = $io->getCleanPath(
                    Mage::getBaseDir() . '/' 
                    . $model->getSimplegoogleshoppingPath() 
                    . $model->getSimplegoogleshoppingFilename()
                );
                
                if ($io->fileExists($filePath)) {
                    $io->rm($filePath);
                }
                
                $model->delete();
                
                $this->_getSession()->addSuccess(
                    Mage::helper('simplegoogleshopping')
                    ->__('The data feed has been deleted.')
                );
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_redirect('*/*/edit', array('simplegoogleshopping_id' => $id));
                return;
            }
        }
        $this->_getSession()->addError(
            Mage::helper('simplegoogleshopping')
            ->__('Unable to find a data feed to delete.')
        );
        $this->_forward('index');
    }

    public function taxonomyAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function reportAction()
    {
        $parameters = array('limit' => Mage::getStoreConfig("simplegoogleshopping/system/preview"), 'display' => true);

        $this->_forward('showReport', NULL, NULL, $parameters);
    }

    public function showReportAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function libraryAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function updaterAction()
    {
        $this->loadLayout()->renderLayout();
    }
}
