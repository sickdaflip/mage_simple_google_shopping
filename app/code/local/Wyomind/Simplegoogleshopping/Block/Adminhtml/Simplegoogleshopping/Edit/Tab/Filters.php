<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Block_Adminhtml_Simplegoogleshopping_Edit_Tab_Filters extends Mage_Adminhtml_Block_Widget_Form
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $model = Mage::getModel('simplegoogleshopping/simplegoogleshopping');

        $model->load($this->getRequest()->getParam('id'));

        $this->setForm($form);
        $form->addFieldset('simplegoogleshopping_form', array('legend' => $this->__('Configuration')));
        $this->setTemplate('simplegoogleshopping/filters.phtml');
        
        if (Mage::registry('simplegoogleshopping_data')) {
            $form->setValues(Mage::registry('simplegoogleshopping_data')->getData());
        }

        return parent::_prepareForm();
    }
    
    public function getOrderedAttributeList()
    {
        /** @var Wyomind_Simplegoogleshopping_Model_Resource_Simplegoogleshopping $resource*/
        $attributeList = Mage::getResourceModel('simplegoogleshopping/simplegoogleshopping')
                            ->getEntityAttributeCollection();
        $attributeList[] = array("attribute_code" => "entity_id", "frontend_label" => "Product Id");
        $attributeList[] = array("attribute_code" => "qty", "frontend_label" => "Quantity");
        $attributeList[] = array("attribute_code" => "is_in_stock", "frontend_label" => "Is in stock");

        usort(
            $attributeList, 
            array('Wyomind_Simplegoogleshopping_Block_Adminhtml_Simplegoogleshopping_Edit_Tab_Filters', 'cmp')
        );

        return $attributeList;
    }
    
    /**
     * Get attribute options
     * 
     * @param string $attributeId
     * @return array
     */
    public function getAttributesOptions($attributeId)
    {
        $options = array();
        
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        $attributeOptions = $attribute->getSource()->getAllOptions();
        
        foreach ($attributeOptions as $attributeOption) {
            if ((string) $attributeOption['value'] != '') {
                $options[] = $attributeOption;
            }
        }
        
        return $options;
    }
    
    protected function cmp($a, $b)
    {
        return ($a['frontend_label'] < $b['frontend_label']) ? -1 : 1;
    }
}
