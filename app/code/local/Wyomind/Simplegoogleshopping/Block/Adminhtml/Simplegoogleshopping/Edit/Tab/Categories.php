<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Block_Adminhtml_Simplegoogleshopping_Edit_Tab_Categories extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $model = Mage::getModel('simplegoogleshopping/simplegoogleshopping');

        $model->load($this->getRequest()->getParam('id'));

        $this->setForm($form);
        $form->addFieldset('simplegoogleshopping_form', array('legend' => $this->__('Categories')));

        $this->setTemplate('simplegoogleshopping/categories.phtml');

        if (Mage::registry('simplegoogleshopping_data')) {
            $form->setValues(Mage::registry('simplegoogleshopping_data')->getData());
        }

        return parent::_prepareForm();
    }
    
    public function dirFiles($directory)
    {
        $dir = dir($directory); //Open Directory
        while (false !== ($file = $dir->read())) { //Reads Directory
            $extension = substr($file, strrpos($file, '.')); // Gets the File Extension
            if ($extension == ".txt") { // Extensions Allowed
                $filesall[$file] = $file; // Store in Array
            }
        }
        $dir->close(); // Close Directory
        asort($filesall); // Sorts the Array
        
        return $filesall;
    }

    /**
     * Get category depth
     * 
     * @param string $categoryPath
     * @return int
     */
    public function getCategoryDepth($categoryPath)
    {
        return count(explode('/', $categoryPath)) - 1;
    }
    
    
    public function getJsonTree()
    {
        $treeCategories = Mage::helper('simplegoogleshopping/categories')->getTree();
        return json_encode($treeCategories);
    }
}
