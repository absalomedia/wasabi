<?php

namespace ABM\Wasabi;

use Analog\Analog;

class Combination extends Prestashop
{
    /**
     * @param string $data
     */
    public function getCombinationData($data)
    {
        $product = substr($data, 0, strpos($data, ','));
        $combinations = [];
        $vars = explode(',', $data);
        Analog::log("Product variables: $data");
        $choices = array_slice($vars, 1);
        $id_product_attribute = $this->getAttribute($product, $choices);
        Analog::log("Product combination: $id_product_attribute");
        $combo_groups = $this->getCombination($product, $id_product_attribute);
        if (!empty($combo_groups) && is_array($combo_groups) && $combo_groups) {
            foreach ($combo_groups as $k => $row) {
                $combinations = $this->buildAttributes($combinations, $id_product_attribute, $row);
            }
        }

        return $combinations;
    }

    /**
     * @param string $product
     */
    private function getAttribute($product, $choices)
    {
        $sql = 'SELECT pac.id_product_attribute from '._DB_PREFIX_.'product_attribute_combination as pac
                LEFT JOIN  '._DB_PREFIX_.'product_attribute as ppa on ppa.id_product_attribute = pac.id_product_attribute
                LEFT JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute = pac.id_attribute)
                WHERE ppa.id_product = '.$product;
        foreach ($choices as $value) {
            $sql .= ' AND pac.id_product_attribute IN
                    ( SELECT pac.id_product_attribute from '._DB_PREFIX_.'product_attribute_combination as pac
                    WHERE pac.id_attribute = '.(int) $value.')  ';
        }
        $sql .= ' GROUP BY pac.id_product_attribute';
        $id_product_attribute = $this->dbConn->fetchColumn($sql);

        return $id_product_attribute;
    }

    /**
     * @param null|string $id_product_attribute
     */
    private function buildAttributes($combinations, $id_product_attribute, $row)
    {
        $combinationSet = [];
        $specific_price = null;

        $combinations['name'] = $row['product_name'];
        $typcheck = ['id_product', 'price', 'base_price', 'price', 'ecotax', 'weight', 'quantity', 'unit_impact', 'minimal_quantity'];

        foreach ($typcheck as $key => $value) {
            ((strpos($value, 'price') !== false) || (strpos($value, 'weight') !== false) || (strpos($value, 'ecotax') !== false) || (strpos($value, 'impact') !== false)) ? $combinations[$value] = (float) $row[$value] : $combinations[$value] = (int) $row[$value];
        }
        $combinations['attributes_values'][$row['id_attribute_group']] = $row['attribute_name'];
        $combinations['attributes'][] = (int) $row['id_attribute'];

        list($combinationSet[(int) $row['id_product_attribute']], $combinations['specific_price']) = $this->getCombinationSpecificPrice($combinationSet, $row, $id_product_attribute);

        $combinations['reference'] = $row['reference'];
        $combinations['available_date'] = $this->getAvailableDate($row);
        $combinations['image'] = $this->getComboImage($id_product_attribute);
        $combinations['final_price'] = $this->getFinalPrice($row, $specific_price);

        return $combinations;
    }

    private function getFinalPrice($row, $specific_price)
    {
        $specific_price['price'] != 0 ? $final_price = (((float) $row['base_price'] + (float) $row['price']) * (((int) 100 - $specific_price['reduction_percent']) / 100)) : $final_price = (float) $row['base_price'] + (float) $row['price'];

        return $final_price;
    }

    private function getAvailableDate($row)
    {
        ($row['available_date'] != '0000-00-00') ? $dater = $row['available_date'] : $dater = '';

        return $dater;
    }

    /**
     * @param null|string $id_product_attribute
     */
    private function getCombinationSpecificPrice($combinationSet, $row, $id_product_attribute)
    {
        // Call getSpecificPrice in order to set $combination_specific_price
                if (!isset($combinationSet[(int) $row['id_product_attribute']])) {
                    $specific_price = $this->getSpecificPrice($id_product_attribute, $row['id_product']);
                    $combinationSet[(int) $row['id_product_attribute']] = true;

                    return [$combinationSet, $specific_price];
                } else {
                    return [false, null];
                }
    }

    /**
     * @param null|string $id_product_attribute
     * @param string      $product
     */
    private function getCombination($product, $id_product_attribute)
    {
        $combo = $this->getAttributeBase($id_product_attribute);

        if (is_array($combo)) {
            foreach ($combo as $key => $value) {
                $combo['base_price'] = (float) Product::getOrderPrice($product);
                $combo['quantity'] = (int) $this->getStockQuantity($product, $id_product_attribute);
                $combo['id_product'] = (int) $product;
                $combo['product_name'] = (int) $this->getProductName($product);
                $pricing = $this->getAttributePricing($id_product_attribute);
                foreach ($pricing as $ki => $val) {
                    $combo[$ki] = $val;
                }
            }
        }

        return $combo;
    }

    /**
     * @param null|string $attribute
     */
    private function getAttributeBase($attribute)
    {
        $sql = 'SELECT ag.id_attribute_group, ag.is_color_group, agl.name AS group_name, agl.public_name AS public_group_name,
                    a.id_attribute, al.name AS attribute_name, a.color AS attribute_color, ag.group_type, pac.id_product_attribute
            FROM '._DB_PREFIX_.'product_attribute_combination pac
            LEFT JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute = pac.id_attribute)
            LEFT JOIN  '._DB_PREFIX_.'attribute_group ag ON (ag.id_attribute_group = a.id_attribute_group)
            LEFT JOIN '._DB_PREFIX_.'attribute_lang al ON (a.id_attribute = al.id_attribute)
            LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (ag.id_attribute_group = agl.id_attribute_group)
            WHERE pac.id_product_attribute='.(int) $attribute;
        $result = $this->dbConn->fetchRowMany($sql);

        return $result;
    }

    /**
     * @param null|string $attribute
     * @param string      $product
     */
    private function getStockQuantity($product, $attribute)
    {
        $sql = 'SELECT stock.quantity from '._DB_PREFIX_.'stock_available as stock WHERE stock.id_product = '.(int) $product.'AND stock.id_product_attribute = '.(int) $attribute;
        $result = $this->dbConn->fetchColumn($sql);

        return $result;
    }

    /**
     * @param null|string $attribute
     */
    private function getAttributePricing($attribute)
    {
        $sql = 'SELECT pas.price, pas.ecotax, pas.weight, pas.default_on, pa.reference, pas.unit_price_impact, 
                pas.minimal_quantity, pas.available_date FROM '._DB_PREFIX_.'product_attribute_shop pas 
                WHERE pas.id_product_attribute = '.(int) $attribute;
        $result = $this->dbConn->fetchRowMany($sql);

        return $result;
    }

    /**
     * @param null|string $id_product_attribute
     * @param string      $id_product
     */
    private function getSpecificPrice($id_product_attribute, $id_product)
    {
        $specific_price = [];
        if ($this->getNumberSpecificPrice($id_product_attribute, $id_product) > 0) {
            $result = $this->getSpecificPriceData($id_product_attribute, $id_product, date('Y-m-d H:i:s'));
            $specific_price['price'] = $result['price'];
            $specific_price['id_product_attribute'] = $result['id_product_attribute'];
            $specific_price['reduction_percent'] = (int) 100 * $result['reduction'];
            $specific_price['reduction_price'] = 0;
            $specific_price['reduction_type'] = $result['reduction_type'];

            return $specific_price;
        }
    }

    /**
     * @param string      $now
     * @param null|string $id_product_attribute
     * @param string      $id_product
     */
    private function getSpecificPriceData($id_product_attribute, $id_product, $now)
    {
        $sql = 'SELECT * FROM '._DB_PREFIX_.'specific_price
                            WWHERE id_product = '.(int) $id_product.'
                            AND id_product_attribute IN (0, '.(int) $id_product_attribute.')
                            AND (
                                   (from = \'0000-00-00 00:00:00\' OR \''.$now.'\' >= from)
                            AND
                                     (to = \'0000-00-00 00:00:00\' OR \''.$now.'\' <= to)
                            ) ';

        $sql .= ' ORDER BY id_product_attribute DESC, from_quantity DESC, id_specific_price_rule ASC';

        $result = $this->dbConn->fetchRow($sql);

        return $result;
    }

    /**
     * @param null|string $id_product_attribute
     * @param string      $id_product
     */
    private function getNumberSpecificPrice($id_product_attribute, $id_product)
    {
        $sql = 'SELECT COUNT(*) FROM '._DB_PREFIX_.'specific_price WHERE id_product = '.(int) $id_product.' AND id_product_attribute = 0';
        $spec = $this->dbConn->fetchColumn($sql);
        if ($spec == 0) {
            $sql = 'SELECT COUNT(*) FROM '._DB_PREFIX_.'specific_price WHERE id_product_attribute = '.(int) $id_product_attribute;
            $spec = $this->dbConn->fetchColumn($sql);
        }

        return $spec;
    }

    /**
     * @param null|string $id_product_attribute
     */
    private function getComboImage($id_product_attribute)
    {
        $sql = 'SELECT pai.id_imageas image
                        FROM '._DB_PREFIX_.'product_attribute_image pai
                        LEFT JOIN '._DB_PREFIX_.'image_lang il ON (il.id_image = pai.id_image)
                        LEFT JOIN '._DB_PREFIX_.'image i ON (i.id_image = pai.id_image)
                        WHERE pai.id_product_attribute = '.(int) $id_product_attribute.' ORDER by i.position';
        $image = $this->dbConn->fetchColumn($sql);
        ($image !== false) ? $imager = (int) $image : $imager = -1;

        return $imager;
    }

    /**
     * @param string $product
     */
    private function getProductName($product)
    {
        $sql = 'SELECT pl.name from '._DB_PREFIX_.'product_lang as pl WHERE pl.id_product = '.(int) $product;
        $result = $this->dbConn->fetchColumn($sql);

        return $result;
    }
}
