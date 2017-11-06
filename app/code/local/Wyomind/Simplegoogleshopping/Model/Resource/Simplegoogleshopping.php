<?php

/**
 * Copyright © 2016 Wyomind. All rights reserved.
 * See LICENSE.txt for license details.
 */
class Wyomind_Simplegoogleshopping_Model_Resource_Simplegoogleshopping extends Mage_Core_Model_Resource_Db_Abstract {

    public $resource;
    public $read;
    public $options = 'ISNULL(options)';
    public $notLike = "AND url.target_path LIKE '%category%'";
    public $concat = 'GROUP_CONCAT';

    public function _construct() {
        // Note that the simplegoogleshopping_id refers to the key field in your database table.
        $this->_init('simplegoogleshopping/simplegoogleshopping', 'simplegoogleshopping_id');

        $this->resource = Mage::getSingleton('core/resource');
        $this->read = $this->resource->getConnection('core_read');

        if (version_compare(Mage::getVersion(), '1.6.0', '<')) {
            $this->options = "options=''";
        }

        if (Mage::getStoreConfig("simplegoogleshopping/system/urlrewrite")) {
            $this->notLike = "AND url.target_path NOT LIKE '%category%'";
            $this->concat = 'MAX';
        }
    }

    /**
     * Get entity_attribute_collection
     * 
     * @return array
     */
    public function getEntityAttributeCollection() {
        /* Recuperer l'id du type d'attributs */
        $tableEet = $this->resource->getTableName('eav_entity_type');
        $select = $this->read->select()->from($tableEet)->where('entity_type_code=\'catalog_product\'');
        $data = $this->read->fetchAll($select);

        $typeId = $data[0]['entity_type_id'];

        /*  Liste des attributs disponible dans la bdd */
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
                ->setEntityTypeFilter($typeId)
                ->addSetInfo()
                ->getData();

        return $attributes;
    }

    /**
     * Get currency rates
     * 
     * @param array $currency
     * @return array
     */
    public function getCurrencyRates($currency) {
        $tableCcp = $this->resource->getTableName('directory_currency_rate');
        $select = $this->read->select()->from($tableCcp)->where('currency_from=\'' . $currency . '\'');
        $currencyRates = $this->read->fetchAll($select);

        return $currencyRates;
    }

    /**
     * Get attribute labels
     * 
     * @param string $storeId
     * @return array
     */
    public function getAttributeLabels($storeId) {
        $tableEaov = $this->resource->getTableName('eav_attribute_option_value');
        $select = $this->read->select();
        $select->from($tableEaov);
        $select->where("store_id=" . $storeId . ' OR  store_id=0');
        $select->order(array('option_id', 'store_id'));
        $attributeLabels = $this->read->fetchAll($select);

        return $attributeLabels;
    }

    /**
     * Get tax rates
     * 
     * @return array
     */
    public function getTaxRates() {
        $tableTc = $this->resource->getTableName('tax_class');
        $tableTcc = $this->resource->getTableName('tax_calculation');
        $tableTcr = $this->resource->getTableName('tax_calculation_rate');
        $tableDcr = $this->resource->getTableName('directory_country_region');
        $tableCg = $this->resource->getTableName('customer_group');


        $select = $this->read->select();
        $select->from($tableTc)->order(array('class_id', 'tax_calculation_rate_id'));
        $select->joinleft(
                array('tc' => $tableTcc), 'tc.product_tax_class_id = ' . $tableTc . '.class_id', 'tc.tax_calculation_rate_id'
        );
        $select->joinleft(
                array('tcr' => $tableTcr), 'tcr.tax_calculation_rate_id = tc.tax_calculation_rate_id', array('tcr.rate', 'tax_country_id', 'tax_region_id')
        );
        $select->joinleft(array('dcr' => $tableDcr), 'dcr.region_id=tcr.tax_region_id', 'code');
        $select->joinInner(
                array('cg' => $tableCg), 'cg.tax_class_id=tc.customer_tax_class_id AND cg.customer_group_code="NOT LOGGED IN"'
        );
        $taxRates = $this->read->fetchAll($select);

        return $taxRates;
    }

    /**
     * Get reviews and rates
     * 
     * @return array
     */
    public function getReviewsAndRates() {
        $tableR = $this->resource->getTableName('review');
        $tableRs = $this->resource->getTableName('review_store');
        $tableRov = $this->resource->getTableName('rating_option_vote');
        $sqlByStoreId = $this->read->select()->distinct('review_id');
        $sqlByStoreId->from(
                array("r" => $tableR), array("COUNT(DISTINCT r.review_id) AS count", 'entity_pk_value')
        );
        $sqlByStoreId->joinleft(array('rs' => $tableRs), 'rs.review_id=r.review_id', 'rs.store_id');
        $sqlByStoreId->joinleft(
                array('rov' => $tableRov), 'rov.review_id=r.review_id', 'AVG(rov.percent) AS score'
        );
        $sqlByStoreId->where("status_id=1 and entity_id=1");
        $sqlByStoreId->group(array('r.entity_pk_value', 'rs.store_id'));

        $sqlAllStoreId = $this->read->select();
        $sqlAllStoreId->from(
                array("r" => $tableR), array("COUNT(DISTINCT r.review_id) AS count", 'entity_pk_value', "(SELECT 0) AS  store_id")
        );
        $sqlAllStoreId->joinleft(array('rs' => $tableRs), 'rs.review_id=r.review_id', array());
        $sqlAllStoreId->joinleft(
                array('rov' => $tableRov), 'rov.review_id=r.review_id', 'AVG(rov.percent) AS score'
        );
        $sqlAllStoreId->where("status_id=1 and entity_id=1");
        $sqlAllStoreId->group(array('r.entity_pk_value'));

        $select = $this->read->select()->union(array($sqlByStoreId, $sqlAllStoreId));
        $select->order(array('entity_pk_value', 'store_id'));

        $reviewsAndRates = $this->read->fetchAll($select);

        return $reviewsAndRates;
    }

    /**
     * Get media gallery
     * 
     * @param string $storeId
     * @return array
     */
    public function getMediaGallery($storeId) {
        $tableCpemg = $this->resource->getTableName('catalog_product_entity_media_gallery');
        $tableCpemgv = $this->resource->getTableName('catalog_product_entity_media_gallery_value');

        $select = $this->read->select(array("DISTINCT value"));
        $select->from($tableCpemg);
        $select->joinleft(
                array('cpemgv' => $tableCpemgv), 'cpemgv.value_id = ' . $tableCpemg . '.value_id', array('cpemgv.position', 'cpemgv.disabled')
        );
        $select->where("value<>TRIM('') AND (store_id=" . $storeId . ' OR  store_id=0)');
        $select->order(array('position', 'value_id'));
        $select->group(array('value_id'));

        $mediaGallery = $this->read->fetchAll($select);

        return $mediaGallery;
    }

    /**
     * Get configurable products from a store id
     * 
     * @param string $storeId
     * @param array $attributes
     * @return array
     */
    public function getConfigurableProducts($storeId, $attributes) {
        $tableCpsl = $this->resource->getTableName('catalog_product_super_link');
        $tableCsi = $this->resource->getTableName("cataloginventory_stock_item");
        $tableCur = $this->resource->getTableName("core_url_rewrite");
        $tableCcp = $this->resource->getTableName('catalog_category_product');
        $tableCcpi = $this->resource->getTableName('catalog_category_product_index');

        $collection = Mage::getModel('simplegoogleshopping/product_collection')->getCollection()
                ->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('type_id', array("in" => "configurable"));
        $collection->addAttributeToFilter('visibility', array("nin" => 1));
        $collection->addAttributeToSelect($attributes, true);
        $collection->getSelect()->joinLeft(
                $tableCpsl . ' AS cpsl', 'cpsl.parent_id=e.entity_id ', array('child_ids' => 'GROUP_CONCAT( DISTINCT cpsl.product_id)')
        );
        $collection->getSelect()->joinLeft(
                $tableCsi . ' AS stock', 'stock.product_id=e.entity_id', array('qty' => 'qty', 'is_in_stock' => 'is_in_stock',
            'manage_stock' => 'manage_stock',
            'use_config_manage_stock' => 'use_config_manage_stock',
            'backorders' => 'backorders',
            'use_config_backorders' => 'use_config_backorders')
        );
        $collection->getSelect()->joinLeft(
                $tableCur . ' AS url', 'url.product_id=e.entity_id ' . $this->notLike . ' AND is_system=1 AND ' . $this->options
                . ' AND url.store_id=' . $storeId, array('request_path' => $this->concat . '(DISTINCT request_path)')
        );
        $collection->getSelect()->joinLeft($tableCcp . ' AS categories', 'categories.product_id=e.entity_id');
        $collection->getSelect()->joinLeft(
                $tableCcpi . ' AS categories_index', 'categories_index.category_id=categories.category_id '
                . 'AND categories_index.product_id=categories.product_id '
                . 'AND categories_index.store_id=' . $storeId, array('categories_ids' => 'GROUP_CONCAT( DISTINCT categories_index.category_id)')
        );

        $collection->getSelect()->group(array('cpsl.parent_id'));

        return $collection;
    }

    /**
     * Get quantity of configurable products
     * 
     * @param string $storeId
     * @return array
     */
    public function getConfigurableQuantity($storeId) {
        $tableCpsl = $this->resource->getTableName('catalog_product_super_link');
        $tableCsi = $this->resource->getTableName("cataloginventory_stock_item");

        $collection = Mage::getModel('simplegoogleshopping/product_collection')->getCollection()
                ->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('type_id', array("in" => "configurable"));
        $collection->addAttributeToFilter('visibility', array("nin" => 1));
        $collection->getSelect()->joinLeft($tableCpsl . ' AS cpsl', 'cpsl.parent_id=e.entity_id ');
        $collection->getSelect()->joinLeft(
                $tableCsi . ' AS stock', 'stock.product_id=cpsl.product_id', array('qty' => 'SUM(stock.qty)')
        );
        $collection->getSelect()->group(array('cpsl.parent_id'));

        return $collection;
    }

    /**
     * Get prices of configurable products
     * 
     * @return array
     */
    public function getConfigurablePrices() {
        $tableCpsl = $this->resource->getTableName('catalog_product_super_link');
        $tableCpsa = $this->resource->getTableName('catalog_product_super_attribute');
        $tableCpei = $this->resource->getTableName('catalog_product_entity_int');
        $tableCpsap = $this->resource->getTableName('catalog_product_super_attribute_pricing');

        $sqlConfigPrices = $this->read->select();
        $sqlConfigPrices->from(array('cpsl' => $tableCpsl), array('parent_id', 'product_id'));
        $sqlConfigPrices->joinleft(
                array('cpsa' => $tableCpsa), 'cpsa.product_id = cpsl.parent_id', array('attribute_id')
        );
        $sqlConfigPrices->joinleft(
                array('cpei' => $tableCpei), 'cpei.entity_id = cpsl.product_id AND cpei.attribute_id = cpsa.attribute_id', array('value' => 'value')
        );
        $sqlConfigPrices->joinleft(
                array('cpsap' => $tableCpsap), 'cpsap.product_super_attribute_id = cpsa.product_super_attribute_id '
                . 'AND cpei.value = cpsap.value_index', array('pricing_value' => 'pricing_value', 'is_percent' => 'is_percent')
        );

        $sqlConfigPrices->order(array('cpsl.parent_id', 'cpsl.product_id'));
        $sqlConfigPrices->group(array('cpsl.parent_id', 'cpsl.product_id', 'cpsa.attribute_id'));

        $configPrices = $this->read->fetchAll($sqlConfigPrices);

        return $configPrices;
    }

    /**
     * Get grouped products from a store id
     * 
     * @param string $storeId
     * @param array $attributes
     * @return array
     */
    public function getGroupedProducts($storeId, $attributes) {
        $tableCsi = $this->resource->getTableName("cataloginventory_stock_item");
        $tableCur = $this->resource->getTableName("core_url_rewrite");
        $tableCcp = $this->resource->getTableName('catalog_category_product');
        $tableCcpi = $this->resource->getTableName('catalog_category_product_index');
        $tableCpl = $this->resource->getTableName('catalog_product_link');

        $collection = Mage::getModel('simplegoogleshopping/product_collection')->getCollection()
                ->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('type_id', array("in" => "grouped"));
        $collection->addAttributeToFilter('visibility', array("nin" => 1));
        $collection->addAttributeToSelect($attributes, true);
        $collection->getSelect()->joinLeft(
                $tableCpl . ' AS cpl', 'cpl.product_id=e.entity_id AND cpl.link_type_id=3', array('child_ids' => 'GROUP_CONCAT( DISTINCT cpl.linked_product_id)')
        );
        $collection->getSelect()->joinLeft(
                $tableCsi . ' AS stock', 'stock.product_id=e.entity_id', array('qty' => 'qty', 'is_in_stock' => 'is_in_stock', 'manage_stock' => 'manage_stock',
            'use_config_manage_stock' => 'use_config_manage_stock', 'backorders' => 'backorders',
            'use_config_backorders' => 'use_config_backorders')
        );
        $collection->getSelect()->joinLeft(
                $tableCur . ' AS url', 'url.product_id=e.entity_id ' . $this->notLike . ' AND is_system=1 '
                . 'AND ' . $this->options . ' AND url.store_id=' . $storeId, array('request_path' => $this->concat . '(DISTINCT request_path)')
        );
        $collection->getSelect()->joinLeft($tableCcp . ' AS categories', 'categories.product_id=e.entity_id');
        $collection->getSelect()->joinLeft(
                $tableCcpi . ' AS categories_index', 'categories_index.category_id=categories.category_id '
                . 'AND categories_index.product_id=categories.product_id '
                . 'AND categories_index.store_id=' . $storeId, array('categories_ids' => 'GROUP_CONCAT( DISTINCT categories_index.category_id)')
        );

        $collection->getSelect()->group(array('cpl.product_id'));

        return $collection;
    }

    /**
     * Get bundle products from a store id
     * 
     * @param string $storeId
     * @param array $attributes
     * @return array
     */
    public function getBundleProducts($storeId, $attributes) {
        $tableCsi = $this->resource->getTableName("cataloginventory_stock_item");
        $tableCur = $this->resource->getTableName("core_url_rewrite");
        $tableCcp = $this->resource->getTableName('catalog_category_product');
        $tableCcpi = $this->resource->getTableName('catalog_category_product_index');
        $tableCpbs = $this->resource->getTableName('catalog_product_bundle_selection');

        $collection = Mage::getModel('simplegoogleshopping/product_collection')->getCollection()
                ->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToFilter('type_id', array("in" => "bundle"));
        $collection->addAttributeToFilter('visibility', array("nin" => 1));
        $collection->addAttributeToSelect($attributes, true);
        $collection->getSelect()->joinLeft(
                $tableCpbs . ' AS cpbs', 'cpbs.parent_product_id=e.entity_id', array('child_ids' => 'GROUP_CONCAT( DISTINCT cpbs.product_id)')
        );
        $collection->getSelect()->joinLeft(
                $tableCsi . ' AS stock', 'stock.product_id=e.entity_id', array('qty' => 'qty', 'is_in_stock' => 'is_in_stock', 'manage_stock' => 'manage_stock',
            'use_config_manage_stock' => 'use_config_manage_stock', 'backorders' => 'backorders',
            'use_config_backorders' => 'use_config_backorders')
        );
        $collection->getSelect()->joinLeft(
                $tableCur . ' AS url', 'url.product_id=e.entity_id ' . $this->notLike
                . ' AND is_system=1 AND ' . $this->options . ' AND url.store_id=' . $storeId, array('request_path' => $this->concat . '(DISTINCT request_path)')
        );
        $collection->getSelect()->joinLeft($tableCcp . ' AS categories', 'categories.product_id=e.entity_id');
        $collection->getSelect()->joinLeft(
                $tableCcpi . ' AS categories_index', 'categories_index.category_id=categories.category_id '
                . 'AND categories_index.product_id=categories.product_id '
                . 'AND categories_index.store_id=' . $storeId, array('categories_ids' => 'GROUP_CONCAT( DISTINCT categories_index.category_id)')
        );
        $collection->getSelect()->group(array('e.entity_id'));

        return $collection;
    }

    /**
     * Get custom options
     * 
     * @return array
     */
    public function getCustomOptions() {
        /* Liste des reviews et rates */
        $tableCpo = $this->resource->getTableName("catalog_product_option");
        $tableCpot = $this->resource->getTableName("catalog_product_option_title");
        $tableCpotv = $this->resource->getTableName("catalog_product_option_type_value");
        $tableCpott = $this->resource->getTableName("catalog_product_option_type_title");
        $tableCpotp = $this->resource->getTableName("catalog_product_option_type_price");

        $sqlCustomOptions = $this->read->select();
        $sqlCustomOptions->from(array("cpo" => $tableCpo), array("product_id"));
        $sqlCustomOptions->joinleft(
                array("cpot" => $tableCpot), "cpot.option_id=cpo.option_id AND cpot.store_id=0", array("option" => "title", "option_id", "store_id")
        );
        $sqlCustomOptions->joinleft(
                array("cpotv" => $tableCpotv), "cpotv.option_id = cpo.option_id", array("sku", "id" => "option_type_id")
        );
        $sqlCustomOptions->joinleft(
                array("cpott" => $tableCpott), "cpott.option_type_id=cpotv.option_type_id AND cpott.store_id=cpot.store_id", "title AS value"
        );
        $sqlCustomOptions->joinleft(
                array("cpotp" => $tableCpotp), "cpotp.option_type_id=cpotv.option_type_id AND cpotp.store_id=cpot.store_id", array("price", "price_type")
        );

        $select = $sqlCustomOptions->order(array("product_id", "cpotv.sort_order ASC"));

        $customOptions = $this->read->fetchAll($select);

        return $customOptions;
    }

    /**
     * Prepare product collection with store/visibility/attributes/categories filters
     * 
     * @param string $storeId
     * @param array $typeIdFilter
     * @param array $visibilityFilter
     * @param array $attributeSetsFilter
     * @param array $attributes
     * @param array $attributesFilter
     * @param type $categoriesFilterList
     * @param string $categoryFilter
     * @param string $categoryType
     * @return Wyomind_Simplegoogleshopping_Model_Product_Collection
     */
    public function prepareProductCollection($storeId, $typeIdFilter, $visibilityFilter, $attributeSetsFilter, $attributes, $attributesFilter, $categoriesFilterList, $categoryFilter, $categoryType
    ) {
        $tableCsi = $this->resource->getTableName("cataloginventory_stock_item");
        $tableCur = $this->resource->getTableName("core_url_rewrite");
        $tableCcp = $this->resource->getTableName('catalog_category_product');
        $tableCcpi = $this->resource->getTableName('catalog_category_product_index');
        $tableCpsl = $this->resource->getTableName('catalog_product_super_link');
        $tableCpip = $this->resource->getTableName('catalog_product_index_price');
        $tableEur = $this->resource->getTableName('enterprise_url_rewrite');
        $manageStock = Mage::getStoreConfig("cataloginventory/item_options/manage_stock", $storeId);

        $condition = array("eq" => "= '%s'",
            "neq" => "!= '%s'",
            "gteq" => ">= '%s'",
            "lteq" => "<= '%s'",
            "gt" => "> '%s'",
            "lt" => "< '%s'",
            "like" => "like '%s'",
            "nlike" => "not like '%s'",
            "null" => "is null",
            "notnull" => "is not null",
            "in" => "in (%s)",
            "nin" => "not in(%s)",
        );
        $where = '';
        $in = "NOT IN";
        $a = 0;


        $collection = Mage::getModel('simplegoogleshopping/product_collection')->getCollection()
                ->addStoreFilter($storeId);
        if (Mage::getStoreConfig('simplegoogleshopping/system/disabled')) {
            $collection->addFieldToFilter('status', array('gteq' => 1));
        } else {
            $collection->addFieldToFilter('status', 1);
        }
        $collection->addAttributeToFilter('type_id', array('in' => $typeIdFilter));
        $collection->addAttributeToFilter('visibility', array('in' => $visibilityFilter));

        if ($attributeSetsFilter[0] != '*') {
            $collection->addAttributeToFilter('attribute_set_id', array('in' => $attributeSetsFilter));
        }

        $collection->addAttributeToSelect($attributes, true);

        $tempFilter = array();
        foreach ($attributesFilter as $attributeFilter) {
            if ($attributeFilter->checked) {
                if ($attributeFilter->condition == 'in' || $attributeFilter->condition == 'nin') {
                    if ($attributeFilter->code == 'qty' || $attributeFilter->code == 'is_in_stock') {
                        $array = explode(',', $attributeFilter->value);
                        $attributeFilter->value = "'" . implode($array, "','") . "'";
                    } else {
                        $attributeFilter->value = explode(',', $attributeFilter->value);
                    }
                }
                switch ($attributeFilter->code) {
                    case 'qty' :
                        if ($a > 0) {
                            $where.=' ' . $attributeFilter->statement . ' ';
                        }
                        $where.=" qty " . sprintf($condition[$attributeFilter->condition], $attributeFilter->value);

                        $a++;
                        break;
                    case 'is_in_stock' :
                        if ($a > 0) {
                            $where.=' ' . $attributeFilter->statement . ' ';
                        }

                        $where.=" (IF(";
                        // use_config_manage_stock=1 && default_manage_stock=0 
                        $where.="(use_config_manage_stock=1 AND $manageStock=0)";

                        // use_config_manage_stock=0 && manage_stock=0
                        $where.=" OR ";
                        $where.='(use_config_manage_stock=0 AND manage_stock=0)';

                        // use_config_manage_stock=1 && default_manage_stock=1 && in_stock=1
                        $where.=" OR ";
                        $where.="(use_config_manage_stock=1 AND $manageStock=1 AND is_in_stock=1 )";

                        // use_config_manage_stock=0 && manage_stock=1 && in_stock=1
                        $where.=" OR ";
                        $where.="(use_config_manage_stock=0 AND manage_stock=1 AND is_in_stock=1 )";
                        $where.=",'1','0')"
                                . sprintf($condition[$attributeFilter->condition], $attributeFilter->value) . ")";

                        $a++;
                        break;
                    default :
                        if ($attributeFilter->statement == "AND") {
                            if (count($tempFilter)) {
                                $collection->addFieldToFilter($tempFilter);
                            }
                            $tempFilter = array();
                        }

                        if ($attributeFilter->condition == "in") {
                            $finset = true;
                            $findInSet = array();
                            foreach ($attributeFilter->value as $v) {
                                if (!is_numeric($v)) {
                                    $finset = true;
                                }
                            }
                            if ($finset) {
                                foreach ($attributeFilter->value as $v) {
                                    $findInSet[] = array(array("finset" => $v));
                                }

                                $tempFilter[] = array("attribute" => $attributeFilter->code, $findInSet);
                            } else {
                                $tempFilter[] = array(
                                    "attribute" => $attributeFilter->code,
                                    $attributeFilter->condition => $attributeFilter->value
                                );
                            }
                        } else {
                            $tempFilter[] = array(
                                "attribute" => $attributeFilter->code,
                                $attributeFilter->condition => $attributeFilter->value
                            );
                        }

                        break;
                }
            }
        }
        if ($where != "") {
            $collection->getSelect()->where($where);
        }
        if (count($tempFilter)) {
            $collection->addFieldToFilter($tempFilter);
        }

        $collection->getSelect()->joinLeft(
                $tableCsi . ' AS stock', 'stock.product_id=e.entity_id', array('qty' => 'qty', 'is_in_stock' => 'is_in_stock', 'manage_stock' => 'manage_stock',
            'use_config_manage_stock' => 'use_config_manage_stock', 'backorders' => 'backorders',
            'use_config_backorders' => 'use_config_backorders')
        );


        if (version_compare(Mage::getVersion(), '1.13.0', '>=')) {
            $collection->getSelect()->joinLeft(
                    $tableEur . ' AS url', 'url.value_id=IF(at_url_key.value_id,at_url_key.value_id,at_url_key_default.value_id) '
                    . $this->notLike . ' AND is_system=1 ', array('request_path' => $this->concat . '(DISTINCT url.request_path)')
            );
        } else {
            $collection->getSelect()->joinLeft(
                    $tableCur . ' AS url', 'url.product_id=e.entity_id ' . $this->notLike
                    . ' AND is_system=1 AND ' . $this->options . ' AND url.store_id=' . $storeId, array('request_path' => $this->concat . '(DISTINCT request_path)')
            );
        }
        
        

        if ($categoriesFilterList[0] != '*') {
            $filter = implode(",",$categoriesFilterList);

            if ($categoryFilter) {
                $in = "IN";
            }
            $collection->getSelect()->joinLeft(
                    $tableCpsl . ' AS cpsl', 'cpsl.product_id=e.entity_id ', array('parent_id' => 'parent_id')
            );
            switch ($categoryType) {
                case 0:
                    $ct = "categories.product_id=e.entity_id";
                    break;
                case 1 :
                    $ct = "categories.product_id=e.entity_id OR categories.product_id=cpsl.parent_id";
                    break;
                case 2:
                    $ct = "categories.product_id=cpsl.parent_id ";
                    break;
            }
            if (version_compare(Mage::getVersion(), '1.12.0', '<=')) {
                $filter = "AND categories_index.category_id " . $in . " (" . $filter . ")";
                $collection->getSelect()->joinLeft($tableCcp . ' AS categories', $ct);
                $collection->getSelect()->joinInner(
                        $tableCcpi . ' AS categories_index', '((categories_index.category_id=categories.category_id '
                        . 'AND categories_index.product_id=categories.product_id)) '
                        . 'AND categories_index.store_id=' . $storeId . ' ' . $filter, array('categories_ids' => 'GROUP_CONCAT( DISTINCT categories_index.category_id)')
                );
            } else {
                $filter = "AND categories.category_id " . $in . " (" . $filter . ")";
                $collection->getSelect()->joinInner(
                        $tableCcp . ' AS categories', $ct . ' ' . $filter, array('categories_ids' => 'GROUP_CONCAT( DISTINCT categories.category_id)')
                );
            }
        } else {
            $collection->getSelect()->joinLeft($tableCcp . ' AS categories', 'categories.product_id=e.entity_id');
            $collection->getSelect()->joinLeft(
                    $tableCcpi . ' AS categories_index', '((categories_index.category_id=categories.category_id '
                    . 'AND categories_index.product_id=categories.product_id)) '
                    . 'AND categories_index.store_id=' . $storeId, array('categories_ids' => 'GROUP_CONCAT(DISTINCT categories_index.category_id)')
            );
        }
        if (version_compare(Mage::getVersion(), '1.4.0', '>=')) {
            $collection->getSelect()->joinLeft(
                    $tableCpip . ' AS price_index', 'price_index.entity_id=e.entity_id AND customer_group_id=0 '
                    . 'AND price_index.website_id=' . Mage::getModel('core/store')->load($storeId)->getWebsiteId(), array('min_price' => 'min_price', 'max_price' => 'max_price',
                'tier_price' => 'tier_price', 'final_price' => 'final_price')
            );
        }

        if (!empty($where)) {
            $collection->getSelect()->where($where);
        }

        $collection->getSelect()->group("e.entity_type_id");

        return $collection;
    }

    /**
     * Get product count from collection
     * 
     * @param Wyomind_Simplegoogleshopping_Model_Product_Collection $collection
     * @return string
     */
    public function getProductCount($collection) {
        $collection->getSelect()->columns("COUNT(DISTINCT e.entity_id) As total");

        return $collection->getFirstItem()->getTotal();
    }

    /**
     * Apply group and order by on product collection
     * 
     * @param Wyomind_Simplegoogleshopping_Model_Product_Collection $collection
     * @return Wyomind_Simplegoogleshopping_Model_Product_Collection
     */
    public function sortProductCollection($collection) {
        $collection->getSelect()->group(array('e.entity_id'))->order('e.entity_id');

        return $collection;
    }

    /**
     * Limit product collection
     * 
     * @param Wyomind_Simplegoogleshopping_Model_Product_Collection $collection
     * @param string $limit
     * @param string $from
     * @return Wyomind_Simplegoogleshopping_Model_Product_Collection
     */
    public function limitProductCollection($collection, $limit, $from) {
        $collection->getSelect()->limit($limit, $from);

        return $collection;
    }

}
