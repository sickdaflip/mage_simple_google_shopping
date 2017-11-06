<?php
/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Block_Adminhtml_Library extends Mage_Adminhtml_Block_Template
{

    public function _ToHtml()
    {
        /** @var Wyomind_Simplegoogleshopping_Model_Resource_Simplegoogleshopping $resource*/
        $attributeList = Mage::getResourceModel('simplegoogleshopping/simplegoogleshopping')
                            ->getEntityAttributeCollection();
        $attributeList[] = array("attribute_code" => "qty", "frontend_label" => "Quantity");
        $attributeList[] = array("attribute_code" => "is_in_stock", "frontend_label" => "Is in stock");
        $attributeList[] = array("attribute_code" => "entity_id", "frontend_label" => "Product ID");
        
        usort($attributeList, array('Wyomind_Simplegoogleshopping_Block_Adminhtml_Library', 'cmp'));

        $tabOutput = '<div id="dfm-library"><ul> ';
        $contentOutput = '<table >';
        $contentOutput .="<tr><td><b>References</b></td></tr>";
        
        foreach ($attributeList as $attribute) {
            if (!empty($attribute['frontend_label'])) {
                $contentOutput.= "<tr><td>" . $attribute['frontend_label'] . "</td>"
                        . "<td><span class='pink'>{" . $attribute['attribute_code'] . "}</span></td></tr>";
            }
        }

        foreach ($attributeList as $attribute) {
            if (!empty($attribute['attribute_code']) && empty($attribute['frontend_label'])) {
                $contentOutput.= "<tr><td>" . $attribute['frontend_label'] . "</td>"
                        . "<td><span class='pink'>{" . $attribute['attribute_code'] . "}</span></td></tr>";
            }
        }

        $tabOutput .=" <h3>Documentation</h3><ul>";
        $tabOutput .=" <li><a class='external_link' target='_blank' href='http://wyomind.com/google-shopping-magento."
                . "html?src=sgs-library&directlink=documentation#Special_attributes'>Special Attributes</a></li>";
        $tabOutput .=" <li><a class='external_link' target='_blank' href='http://wyomind.com/google-shopping-magento."
                . "html?src=sgs-library&directlink=documentation#Basic_attributes_&_basic_options'>Attribute options"
                . "</a></li>";
        $tabOutput .=" <li><a class='external_link' target='_blank' href='http://wyomind.com/google-shopping-magento."
                . "html?src=sgs-library&directlink=documentation#Simple_Google_Shopping_tutorial'>Tutorial</a></li>";
        $tabOutput .="</ul>";

        $contentOutput .="</table></div>";
        $tabOutput .= '</ul>';
        return($tabOutput . $contentOutput);
    }
    
    protected function cmp($a, $b)
    {
        return ($a['frontend_label'] < $b['frontend_label']) ? -1 : 1;
    }
}
