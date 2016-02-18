<?php

namespace ABM\Wasabi;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Analog\Analog;

class Prestashop implements MessageComponentInterface
{
    protected $clients;
    protected $dbConn;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        // Now connect to PS DB using Simplon on Composer
        $this->dbConn = new \Simplon\Mysql\Mysql(
    '127.0.0.1',
    _DB_USER_,
    _DB_PASSWD_,
    _DB_NAME_
        );
        $log_file = 'wasabi.log';
        Analog::handler(\Analog\Handler\File::init($log_file));
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        Analog::log("New connection: $conn->resourceId");
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $result = null;
        foreach ($this->clients as $client) {
            if ($from == $client) {
                $type = strtolower(substr($msg, 0, strpos($msg, '|')));
                $data = substr($msg, strpos($msg, '|') + 1);
                switch ($type) {
                    case 'cart': $result = $this->getCartData($data);
                                    break;
                    case 'prod': $result = $this->getProductData($data);
                                    break;
                    case 'comb': $result = $this->getCombinationData($data);
                                    break;
                    default:        break;
                }
                if (!empty($result)) {
                $client->send(json_encode($result));
                }
            }
        }
    }

    /**
     * @param string $data
     */
    private function getCombinationData($data) {
        $product = substr($data, 0, strpos($data, ','));
        $combinations = array();
                $vars = explode(',', $data);
                Analog::log("Product variables: $data");
                $choices = array_slice($vars, 1);
                $id_product_attribute = $this->getAttribute($product, $choices);
                Analog::log("Product combination: $id_product_attribute");
                $combo_groups = $this->getCombination($id_product_attribute);
                if (!empty($combo_groups) && is_array($combo_groups) && $combo_groups) {
                    foreach ($combo_groups as $k => $row) {
                        $combinations = $this->buildAttributes($combinations, $id_product_attribute, $row);
                    }
                }
        return $combinations;
    }

    /**
     * @param string $data
     */
    private function getProductData($data) {
                $category = (int) substr($data, 0, strpos($data, ','));
                if ($category != 0) {
                    $products = $this->getProducts($category);
                } else {
                    $product = substr($data, strpos($data, ',') + 1);
                    $products = $this->getProducts($product);
                }
                Analog::log("Product variables: $data");
        return $products;
    }

    /**
     * @param string $data
     */
    private function getCartData($data) {
        $cart = substr($data, 0, strpos($data, ','));
        $cust = substr($data, strpos($data, ',') + 1);

        Analog::log("Cart & customer variables: $data");
        $otherCarts = $this->processFindCarts($cart, $cust);
        return $otherCarts;
    }


/**
 * @param string $cart
 * @param string $cust
 */
    private function processFindCarts($cart, $cust)
    {
        $sql = 'SELECT DISTINCT pc.id_cart as id, DATE_FORMAT(pc.date_upd,"%a %D %b %Y, %l:%i %p") as timer from '._DB_PREFIX_.'cart as pc
                LEFT JOIN  '._DB_PREFIX_.'cart_product as pcp on pcp.id_cart = pc.id_cart
                WHERE pc.id_cart NOT IN (SELECT po.id_cart FROM  '._DB_PREFIX_.'orders as po)
                AND pcp.id_product IS NOT NULL
                AND pc.id_customer = '.(int) $cust.'
                AND pc.id_cart != '.(int) $cart.'
                ORDER BY pc.date_upd DESC
                LIMIT 10';
        if ($results = $this->dbConn->fetchRowMany($sql)) {
            foreach ($results as &$row) {
                $row['token'] = md5(_COOKIE_KEY_.'recover_cart_'.$row['id']);
            }

            return $results;
        } else {
            return;
        }
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
        $combinationSet = array();
        $specific_price = null;

        $combinations['name'] = $row['product_name'];
        $typcheck = array("id_product", "price", "base_price", "price", "ecotax","weight","quantity","unit_impact","minimal_quantity");

        foreach ($typcheck as $key=>$value) 
        { 
            if ( (strpos($value, 'price') !== false) || (strpos($value, 'weight') !== false) || (strpos($value, 'ecotax') !== false) || (strpos($value, 'impact') !== false) )
            {
                $combinations[$value] = (float) $row[$value]; 
            } else {
                $combinations[$value] = (int) $row[$value]; 
            }
        }
        $combinations['attributes_values'][$row['id_attribute_group']] = $row['attribute_name'];
        $combinations['attributes'][] = (int) $row['id_attribute'];

        list ($combinationSet[(int) $row['id_product_attribute']], $combinations['specific_price']) = $this->getCombinationSpecificPrice($combinationSet, $row, $id_product_attribute);

        $combinations['reference'] = $row['reference'];
        $combinations['available_date'] = $this->getAvailableDate($row);
        $combinations['image'] = $this->getComboImage($id_product_attribute);
        $combinations['final_price'] = $this->getFinalPrice($row, $specific_price);

        return $combinations;
    }

    private function getFinalPrice($row, $specific_price)
    {
        if ($specific_price['price'] != 0) {
            $final_price = (((float) $row['base_price'] + (float) $row['price']) * (((int) 100 - $specific_price['reduction_percent']) / 100));
        } else {
            $final_price = (float) $row['base_price'] + (float) $row['price'];
        }

        return $final_price;
    }


    private function getAvailableDate($row) {
        if ($row['available_date'] != '0000-00-00') {
            return $row['available_date'];
        } else {
            return '';
        }

    }

    /**
     * @param null|string $id_product_attribute
     */
    private function getCombinationSpecificPrice($combinationSet, $row, $id_product_attribute) {
            // Call getSpecificPrice in order to set $combination_specific_price
                if (!isset($combinationSet[(int) $row['id_product_attribute']])) {
                    $specific_price = $this->getSpecificPrice($id_product_attribute, $row['id_product']);
                    $combinationSet[(int) $row['id_product_attribute']] = true;
                    return array($combinationSet, $specific_price);
                } else {
                    return array(false, null);
                }
    }



    /**
     * @param null|string $id_product_attribute
     */
    private function getCombination($id_product_attribute)
    {
        $sql = 'SELECT pl.name as product_name, pa.id_product, ag.id_attribute_group, ag.is_color_group, agl.name AS group_name, agl.public_name AS public_group_name,
                    a.id_attribute, al.name AS attribute_name, a.color AS attribute_color, pas.id_product_attribute,
                    IFNULL(stock.quantity, 0) as quantity, pas.price, pas.ecotax, pas.weight,
                    pas.default_on, pa.reference, pas.unit_price_impact as unit_impact,
                    pas.minimal_quantity, pas.available_date, ag.group_type
                FROM '._DB_PREFIX_.'product_attribute pa
                 INNER JOIN '._DB_PREFIX_.'product_attribute_shop pas
                    ON (pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = 1)
                 LEFT JOIN '._DB_PREFIX_.'stock_available stock
                        ON (stock.id_product = pa.id_product AND stock.id_product_attribute = IFNULL(pa.id_product_attribute, 0) AND stock.id_shop = 1  )
                LEFT JOIN '._DB_PREFIX_.'product_lang pl ON ( pl.id_product = pa.id_product)
                LEFT JOIN '._DB_PREFIX_.'product_attribute_combination pac ON (pac.id_product_attribute = pa.id_product_attribute)
                LEFT JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute = pac.id_attribute)
                LEFT JOIN '._DB_PREFIX_.'attribute_group ag ON (ag.id_attribute_group = a.id_attribute_group)
                LEFT JOIN '._DB_PREFIX_.'attribute_lang al ON (a.id_attribute = al.id_attribute)
                LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (ag.id_attribute_group = agl.id_attribute_group)
                 INNER JOIN '._DB_PREFIX_.'attribute_shop attribute_shop
                    ON (attribute_shop.id_attribute = a.id_attribute AND attribute_shop.id_shop = 1)
                WHERE pas.id_product_attribute= '.(int) $id_product_attribute.'
                GROUP BY id_attribute_group, id_product_attribute
                ORDER BY ag.position ASC, a.position ASC, agl.name ASC';

        $combo_groups = $this->dbConn->fetchRowMany($sql);

        return $combo_groups;
    }

    /**
     * @param null|string $id_product_attribute
     * @param string $id_product
     */
    private function getSpecificPrice($id_product_attribute, $id_product)
    {
        $specific_price = array();
        if ($this->getNumberSpecificPrice($id_product_attribute, $id_product) > 0) {
            $result = $this->getSpecificPriceData($id_product_attribute, $id_product, date('Y-m-d H:i:s'));
            $specific_price['price'] = $result['price'];
            $specific_price['id_product_attribute'] = $result['id_product_attribute'];
            $specific_price['reduction_percent'] = (int) 100 * $result['reduction'];
            $specific_price['reduction_price'] = 0;
            $specific_price['reduction_type'] = $result['reduction_type'];

            return $specific_price;
        } else {
            return;
        }
    }


    /**
     * @param string $now
     * @param null|string $id_product_attribute
     * @param string $id_product
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
     * @param string $id_product
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
        if ($image !== false) {
            return (int) $image;
        } else {
            return -1;
        }
    }

    /**
     * @param string $category
     */
    private function getProducts($category)
    {
        $product_ids = $this->getProductIDs($category);
        $products = $this->getProduct($product_ids);

        return $products;
    }

    /**
     * @param string $category
     */
    private function getProductIDs($category)
    {
        $sql = 'SELECT DISTINCT p.id_product
                from '._DB_PREFIX_.'product as p
                LEFT JOIN '._DB_PREFIX_.'image AS i ON i.id_product = p.id_product 
                LEFT JOIN '._DB_PREFIX_.'product_lang as pl ON pl.id_product = p.id_product
                WHERE p.active = 1
                AND p.id_category_default = '.(int) $category.'
                GROUP BY p.id_product';
        $pcats = $this->dbConn->fetchRowMany($sql);
        $ids = '';
        if (is_array($pcats) && (!empty($pcats))) {
        foreach ($pcats as $row) {
            $ids .= $row['id_product'].',';
            }
        }

        $ids = rtrim($ids, ',');

        return $ids;
    }

    /**
     * @param string $ids
     */
    private function getProduct($ids)
    {
        $sqler = 'SELECT p.id_product, p.id_supplier, p.ean13, p.upc, p.price, p.wholesale_price, p.on_sale, p.quantity, p.id_category_default, 
                    p.show_price, p.available_for_order, p.minimal_quantity, p.customizable,
                    p.out_of_stock, pl.link_rewrite, pl.name, i.id_image, il.legend,
                    cl.name AS category_default,  cl.id_category AS cat_id, ps.price AS orderprice
                    FROM '._DB_PREFIX_.'product as p                 
                    LEFT JOIN '._DB_PREFIX_.'image AS i ON i.id_product = p.id_product 
                    LEFT JOIN '._DB_PREFIX_.'product_shop as ps ON ps.id_product = p.id_product
                    LEFT JOIN '._DB_PREFIX_.'product_lang as pl ON pl.id_product = p.id_product
                    LEFT JOIN '._DB_PREFIX_.'image_lang as il ON i.id_image = il.id_image
                    LEFT JOIN '._DB_PREFIX_.'category_lang cl ON p.id_category_default = cl.id_category
                    WHERE p.id_product IN ('.$ids.')
                    AND i.cover = 1
                    AND p.active = 1
                    GROUP BY p.id_product
                    ORDER BY p.price ASC';

        $result = $this->dbConn->fetchRowMany($sqler);

        return $result;
    }


    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        Analog::log('Connection '.$conn->resourceId.' has disconnected');
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        Analog::log('Error: '.$e->getMessage().'');
        $conn->close();
    }
}
