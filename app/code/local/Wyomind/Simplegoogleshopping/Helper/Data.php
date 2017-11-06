<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Helper_Data extends Mage_Core_Helper_Data {

    const LOG_FILE = "SimpleGoogleShopping.log";

    public function checkHeartbeat() {
        $lastHeartbeat = $this->getLastHeartbeat();
        if ($lastHeartbeat === false) {
            // no cron task found
            Mage::getSingleton('core/session')->addError(
                    $this->__(
                            'No cron task found. '
                            . '<a href="https://www.wyomind.com/faq.html#How_do_I_fix_the_issues_with_scheduled_tasks" '
                            . 'target="_blank">Check if cron is configured correctly.</a>'
                    )
            );
        } else {
            $timespan = $this->dateDiff($lastHeartbeat);
            if ($timespan <= 5 * 60) {
                Mage::getSingleton('core/session')->addSuccess(
                        $this->__('Scheduler is working. (Last cron task: %s minute(s) ago)', round($timespan / 60))
                );
            } elseif ($timespan > 5 * 60 && $timespan <= 60 * 60) {
                // cron task wasn't executed in the last 5 minutes. 
                // Heartbeat schedule could have been modified to not run every five minutes!
                Mage::getSingleton('core/session')->addNotice(
                        $this->__('Last cron task is older than %s minutes.', round($timespan / 60))
                );
            } else {
                // everything ok
                Mage::getSingleton('core/session')->addError(
                        $this->__(
                                'Last cron task is older than one hour. '
                                . 'Please check your settings and your configuration!'
                        )
                );
            }
        }
    }

    public function getLastHeartbeat() {
        $schedules = Mage::getModel('cron/schedule')->getCollection();
        /** @var Mage_Cron_Model_Resource_Schedule_Collection $schedules */
        $schedules->setPageSize(1);
        $schedules->setOrder('executed_at', 'DESC');
        $schedules->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_SUCCESS);
        $schedules->load();

        if ($schedules->getSize() == 0) {
            return false;
        }

        $executedAt = $schedules->getFirstItem()->getExecutedAt();
        $value = Mage::getSingleton('core/date')->date(NULL, $executedAt);

        return $value;
    }

    public function dateDiff($firstTime, $secondTime = NULL) {
        $firstTimestamp = strtotime($firstTime);
        $secondTimestamp = strtotime($secondTime);

        if (null === $secondTime) {
            $secondTimestamp = Mage::getSingleton('core/date')->timestamp();
        }

        return $secondTimestamp - $firstTimestamp;
    }

    public function getDuration($time) {
        if ($time < 60) {
            $time = ceil($time) . ' sec. ';
        } else {
            $time = floor($time / 60) . ' min. ' . ($time % 60) . ' sec.';
        }
        return $time;
    }

    public function generationStats($googleshopping) {
        $filePath = preg_replace(
                '/^\//', '', $googleshopping->getSimplegoogleshoppingPath() . $googleshopping->getSimplegoogleshoppingFilename()
        );

        $url = (Mage::app()->getStore($googleshopping->getStoreId())
                        ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . $filePath);

        $io = new Varien_Io_File();

        if ($io->fileExists(BP . DS . $filePath)) {
            $report = unserialize($googleshopping->getSimplegoogleshoppingReport());
            $errors = count($report['required']) + count($report['toolong']) + count($report['toomany']) + count($report['invalid']);
            $warnings = count($report['recommended']);
            $time = $report['stats'][1];
            $items = $report['stats'][0];

            $stats = Mage::helper('simplegoogleshopping')->__(
                    '%s product%s exported in %s', $items, ($items > 1) ? "s" : null, Mage::helper('simplegoogleshopping')->getDuration($time)
            );
            $urlTime = Mage::getSingleton('core/date')->gmtTimestamp();

            if ($report == null) {
                $message = Mage::helper('simplegoogleshopping')
                        ->__('The data feed must be generated prior to any report.');

                return '<a href="' . $url . '?r=' . $urlTime . '" target="_blank">'
                        . $url . '</a><br>[ ' . $message . " ]";
            } elseif (!($errors + $warnings)) {
                return '<a href="' . $url . '?r=' . $urlTime . '" target="_blank">' . $url . '</a><br>'
                        . '[ ' . $stats . ", " . Mage::helper('simplegoogleshopping')->__('no error detected') . ' ]';
            } else {
                return '<a href="' . $url . '?r=' . $urlTime . '" target="_blank">' . $url . '</a><br>'
                        . '[ ' . $stats . ", " . $errors . " "
                        . Mage::helper('simplegoogleshopping')->__('error%s', ($errors > 1) ? "s" : null)
                        . ', ' . $warnings . ' '
                        . Mage::helper('simplegoogleshopping')->__('warning%s', ($warnings > 1) ? "s" : null) . ' ]';
            }
        } else {
            return $url . "<br> [ " . Mage::helper('simplegoogleshopping')->__('no report available') . " ]";
        }
    }

    public function reportToHtml($data) {
        $notice = Mage::helper('simplegoogleshopping')->__(
                "This report does not replace the error report from Google"
                . " and is by no means a guarantee that your data feed will be approved by the Google team."
        );
        $html = null;
        foreach ($data["xml-error"] as $error) {
            $html.="<h3>" . $error['message'] . " [" . $error['count'] . " "
                    . Mage::helper('simplegoogleshopping')->__("items") . "]</h3>";
            if ($error['skus'] != "") {
                $html.="<div class='exemples'>" . Mage::helper('simplegoogleshopping')->__("Examples:")
                        . " <b>" . $error['skus'] . "</b></div>";
            }
        }
        foreach ($data["required"] as $error) {
            $html.="<h3>" . $error['message'] . " [" . $error['count'] . " "
                    . Mage::helper('simplegoogleshopping')->__("items") . "]</h3>";
            if ($error['skus'] != "") {
                $html.="<div class='exemples'>" . Mage::helper('simplegoogleshopping')->__("Examples:")
                        . " <b>" . $error['skus'] . "</b></div>";
            }
        }
        foreach ($data["recommended"] as $error) {
            $html.="<h3 style='color:orangered'>" . $error['message'] . " [" . $error['count'] . " "
                    . Mage::helper('simplegoogleshopping')->__("items") . "]</h3>";
            if ($error['skus'] != "") {
                $html.="<div class='exemples'>" . Mage::helper('simplegoogleshopping')->__("Examples:")
                        . " <b>" . $error['skus'] . "</b></div>";
            }
        }
        foreach ($data["toomany"] as $error) {
            $html.="<h3>" . $error['message'] . " [" . $error['count'] . " "
                    . Mage::helper('simplegoogleshopping')->__("items") . "]</h3>";
            if ($error['skus'] != "") {
                $html.="<div class='exemples'>" . Mage::helper('simplegoogleshopping')->__("Examples:")
                        . " <b>" . $error['skus'] . "</b></div>";
            }
        }
        foreach ($data["toolong"] as $error) {
            $html.="<h3>" . $error['message'] . " [" . $error['count'] . " "
                    . Mage::helper('simplegoogleshopping')->__("items") . "]</h3>";
            if ($error['skus'] != "") {
                $html.="<div class='exemples'>" . Mage::helper('simplegoogleshopping')->__("Examples:")
                        . " <b>" . $error['skus'] . "</b></div>";
            }
        }
        foreach ($data["invalid"] as $error) {
            $html.="<h3>" . $error['message'] . " [" . $error['count'] . " "
                    . Mage::helper('simplegoogleshopping')->__("items") . "]</h3>";
            if ($error['skus'] != "") {
                $html.="<div class='exemples'>" . Mage::helper('simplegoogleshopping')->__("Examples:")
                        . " <b>" . $error['skus'] . "</b></div>";
            }
        }

        if ($data == null) {
            return "<div id='dfm-report'>" . $html . "<br><br><b>"
                    . Mage::helper('simplegoogleshopping')->__('The data feed must be generated prior to any report.')
                    . "</b></div>";
        } elseif ($html !== null) {
            return "<div id='dfm-report'>" . $notice . $html . "</div>";
        } else {
            return "<div id='dfm-report'>" . $notice . "<br><br><b>"
                    . Mage::helper('simplegoogleshopping')->__('no error detected.')
                    . "</b></div>";
        }
    }

    /**
     * @return array $requirements
     */
    public function getRequirements() {
        $requirements = array(
            /* Basic Product Information */
            array(
                "label" => "id",
                "tag" => "g_id",
                "required" => true,
                "occurrence" => 1,
                "length" => 50,
                "type" => "Alphanumeric",
            ),
            array(
                "label" => "title",
                "tag" => "title",
                "required" => true,
                "occurrence" => 1,
                "length" => 150,
                "type" => "Text",
            ),
            array(
                "label" => "description",
                "tag" => "description",
                "required" => true,
                "occurrence" => 1,
                "length" => 5000,
                "type" => "Text",
            ),
            array(
                "label" => "google_product_category",
                "tag" => "g_google_product_category",
                "required" => true,
                "occurrence" => 1,
                "type" => "GoogleProductCategory",
            ),
            array(
                "label" => "product_type",
                "tag" => "g_product_type",
                "required" => false,
                "recommended" => true,
                "occurrence" => 9,
                "length" => 750,
                "type" => "Text",
            ),
            array(
                "label" => "link",
                "tag" => "link",
                "required" => true,
                "occurrence" => 1,
                "length" => 2000,
                "type" => "Url",
            ),
            array(
                "label" => "mobile_link",
                "tag" => "g_mobile_link",
                "required" => false,
                "recommended" => false,
                "occurrence" => 1,
                "length" => 2000,
                "type" => "Url",
            ),
            array(
                "label" => "image_link",
                "tag" => "g_image_link",
                "required" => true,
                "occurrence" => 1,
                "length" => 2000,
                "type" => "Url",
            ),
            array(
                "label" => "additional_image_link",
                "tag" => "g_additional_image_link",
                "required" => false,
                "recommended" => false,
                "occurrence" => 9,
                "length" => 2000,
                "type" => "Url",
            ),
            array(
                "label" => "condition",
                "tag" => "g_condition",
                "required" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "new|used|refurbished",
                "say" => "new,used or refurbished"
            ),
            /* Availability & Price */
            array(
                "label" => "availability",
                "tag" => "g_availability",
                "required" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "in stock|out of stock|preorder",
                "say" => "in stock,out of stock or preorder"
            ),
            array(
                "label" => "availability_date",
                "tag" => "g_availability_date",
                "required" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}[-|+]{1}[0-9]{4}",
                "say" => "YYYY-MM-DDThh:mm+hh:mm",
                "depends" => array("availability" => array("eq" => "preorder"))
            ),
            array(
                "label" => "price",
                "tag" => "g_price",
                "required" => true,
                "occurrence" => 1,
                "type" => "Price"
            ),
            array(
                "label" => "sale_price",
                "tag" => "g_sale_price",
                "required" => false,
                "occurrence" => 1,
                "type" => "Price",
            ),
            array(
                "label" => "sale_price_effective_date",
                "tag" => "g_sale_price_effective_date",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "say" => "YYYY-MM-DDThh:mm+hh:mm/YYYY-MM-DDThh:mm+hh:mm",
                "regexp" => "20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:"
                . "[0-9]{2}[\-+]{1}[0-9]{4}\/20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}[\-+]{1}[0-9]{4}"
            ),
            /* Unique Product Identifiers */
            array(
                "label" => "brand",
                "tag" => "g_brand",
                "required" => true,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 70,
                "type" => "Text",
                "depends" => array(
                    "identifier_exists" => array("neq" => "FALSE"),
                    "google_product_category" => array("like" => "Apparel & Accessories")
                )
            ),
            array(
                "label" => "brand",
                "tag" => "g_brand",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 70,
                "type" => "Text",
                "depends" => array("identifier_exists" => array("neq" => "FALSE"))
            ),
            array(
                "label" => "gtin",
                "tag" => "g_gtin",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 50,
                "type" => "RegExp",
                "regexp" => "[0-9]{8}|[0-9]{12}|[0-9]{13}",
                "say" => "8,12 or 13 digit number",
                "depends" => array("identifier_exists" => array("neq" => "FALSE"))
            ),
            array(
                "label" => "mpn",
                "tag" => "g_mpn",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 70,
                "type" => "Alphanumeric",
                "depends" => array("identifier_exists" => array("neq" => "FALSE"))
            ),
            array(
                "label" => "identifier_exists",
                "tag" => "g_identifier_exists",
                "required" => false,
                "occurrence" => 1,
                "type" => "Boolean",
            ),
            /* Apparel Products */
            array(
                "label" => "gender",
                "tag" => "g_gender",
                "required" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "male|female|unisex",
                "say" => "male,female or unisex",
                "depends" => array("google_product_category" => array("like" => "Apparel & Accessories"))
            ),
            array(
                "label" => "age_group",
                "tag" => "g_age_group",
                "required" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "newborn|infant|toddler|kids|adult",
                "say" => "newborn, infant, toddler, kids or adult",
                "depends" => array("google_product_category" => array("like" => "Apparel & Accessories"))
            ),
            array(
                "label" => "color",
                "tag" => "g_color",
                "required" => true,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text",
                "depends" => array("google_product_category" => array("like" => "Apparel & Accessories"))
            ),
            array(
                "label" => "size",
                "tag" => "g_size",
                "required" => true,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text",
                "depends" => array(
                    "google_product_category" => array(
                        "like" => "Apparel & Accessories > Clothing",
                        "like" => "Apparel & Accessories > Shoes"
                    )
                )
            ),
            array(
                "label" => "size",
                "tag" => "g_size",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text",
                "depends" => array("google_product_category" => array("like" => "Apparel & Accessories"))
            ),
            array(
                "label" => "size_type",
                "tag" => "g_size_type",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "regular|petite|plus|big and tall|maternity",
                "say" => "regular, petite, plus, big and tall or maternity",
                "depends" => array(
                    "google_product_category" => array(
                        "like" => "Apparel & Accessories > Clothing",
                        "like" => "Apparel & Accessories > Shoes"
                    )
                )
            ),
            array(
                "label" => "size_system",
                "tag" => "g_size_system",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "US|UK|EU|DE|FR|JP|CN|IT|BR|MEX|AU",
                "say" => "US, UK, EU, DE, FR, JP, CN, IT, BR, MEX or AU",
                "depends" => array(
                    "google_product_category" => array(
                        "like" => "Apparel & Accessories > Clothing",
                        "like" => "Apparel & Accessories > Shoes"
                    )
                )
            ),
            /* Product Variants */
            array(
                "label" => "item_group_id",
                "tag" => "g_item_group_id",
                "required" => false,
                "occurrence" => 1,
                "length" => 50,
                "type" => "Alphanumeric",
            ),
            array(
                "label" => "color",
                "tag" => "g_color",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            array(
                "label" => "size",
                "tag" => "g_size",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            array(
                "label" => "material",
                "tag" => "g_material",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 200,
                "type" => "Text",
                "depends" => array("google_product_category" => array("like" => "Apparel & Accessories"))
            ),
            array(
                "label" => "pattern",
                "tag" => "g_pattern",
                "required" => false,
                "recommended" => true,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text",
                "depends" => array("google_product_category" => array("like" => "Apparel & Accessories"))
            ),
            /* Tax & Shipping */
            array(
                "label" => "shipping",
                "tag" => "g_shipping",
                "required" => false,
                "occurrence" => 1,
            ),
            array(
                "label" => "shipping_weight",
                "tag" => "g_shipping_weight",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "[0-9\.]+lb|oz|g|kg",
                "say" => "Number + weight type (lb, oz, g or kg)",
            ),
            array(
                "label" => "shipping_label",
                "tag" => "g_shipping_label",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            /* Product combinaison */
            array(
                "label" => "multipack",
                "tag" => "g_multipack",
                "required" => false,
                "occurrence" => 1,
                "type" => "Integer"
            ),
            array(
                "label" => "is_bundle",
                "tag" => "g_is_bundle",
                "required" => false,
                "occurrence" => 1,
                "type" => "Boolean",
            ),
            /* Adult products */
            array(
                "label" => "adult",
                "tag" => "g_adult",
                "required" => false,
                "occurrence" => 1,
                "type" => "Boolean",
            ),
            /* Adwords campaign */
            array(
                "label" => "adwords_redirect",
                "tag" => "g_adwords_redirect",
                "required" => false,
                "occurrence" => 1,
                "length" => 2000,
                "type" => "Url",
            ),
            array(
                "label" => "custom_label_0",
                "tag" => "g_custom_label_0",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            array(
                "label" => "custom_label_1",
                "tag" => "g_custom_label_1",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            array(
                "label" => "custom_label_2",
                "tag" => "g_custom_label_2",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            array(
                "label" => "custom_label_3",
                "tag" => "g_custom_label_3",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            array(
                "label" => "custom_label_4",
                "tag" => "g_custom_label_4",
                "required" => false,
                "occurrence" => 1,
                "length" => 100,
                "type" => "Text"
            ),
            /* Additional Attributes */
            array(
                "label" => "excluded_destination",
                "tag" => "g_excluded_destination",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "shopping",
            ),
            array(
                "label" => "expiration_date",
                "tag" => "g_expiration_date",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "20[0-9]{2}-[0-9]{2}-[0-9]{2}",
                "say" => "YYYY-MM-dd",
            ),
            /* Unit prices */
            array(
                "label" => "unit_pricing_measure",
                "tag" => "g_unit_pricing_measure",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "[0-9\.]+mg|g|kg|ml|cl|l|cbm|cm|m|sqm",
                "say" => "Number + measure type (mg, g, kg, ml, cl, l, cbm, cm, m or sqm)",
            ),
            array(
                "label" => "unit_pricing_base_measure",
                "tag" => "g_unit_pricing_base_measure",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "[0-9\.]+mg|g|kg|ml|cl|l|cbm|cm|m|sqm",
                "say" => "Number + measure type (mg, g, kg, ml, cl, l, cbm, cm, m or sqm)",
            ),
            /* Energy Labels */
            array(
                "label" => "energy_efficiency_class",
                "tag" => "g_energy_efficiency_class",
                "required" => false,
                "occurrence" => 1,
                "type" => "RegExp",
                "regexp" => "G|F|E|D|C|B|A|A+|A++|A+++",
                "say" => "G, F, E, D, C, B, A, A+, A++ or  A+++",
            ),
            /* Merchant Promotions Attribute */
            array(
                "label" => "promotion_id",
                "tag" => "g_promotion_id",
                "required" => false,
                "occurrence" => 1,
                "length" => INF,
                "type" => "Text"
            ),
        );

        return $requirements;
    }

    public function getLogFile() {
        return self::LOG_FILE;
    }

    public function childPrice($product) {
        /*
         * Must be used in price.html template as follows in combination with the {config_url} variable
         * list($price,$finalPrice)=Mage::helper("simplegoogleshopping/data")->childPrice($_product); 
         * $_product->setPrice($price);
         * $_product->setFinalPrice($finalPrice);
         */
        $price = $product->getPrice();
        $finalPrice = $product->getFinalPrice();
        $percentage = $finalPrice / $price;
        $childId = Mage::app()->getRequest()->getParam("c");
        if ($childId) {

            $attributeArrays = ($product->getTypeInstance(true)->getConfigurableAttributesAsArray($product));
            foreach ($attributeArrays as $attribute) {

                if (($optionValue = Mage::app()->getRequest()->getParam($attribute["attribute_id"])) !== FALSE) {

                    foreach ($attribute["values"] as $value) {

                        if ($optionValue == $value["value_index"]) {

                            if (!$value['is_percent']) {
                                $price+=$value["pricing_value"];
                                $finalPrice+=$value["pricing_value"] * $percentage;
                            } else {
                                $price+=$price * $value["pricing_value"] / 100;
                                $finalPrice+=$specialPrice * $value["pricing_value"] / 100 * $percentage;
                            }
                            break;
                        }
                    }
                }
            }
        }
        Mage::app()->getRequest()->setParam("c", false);
        return array($price, $finalPrice);
    }

}
