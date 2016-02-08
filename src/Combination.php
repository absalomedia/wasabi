<?php

namespace ABM\Wasabi;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Analog\Analog;

class Combination implements MessageComponentInterface
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
        $log_file = 'ws-comb.log';
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
        foreach ($this->clients as $client) {
            if ($from == $client) {
                $product = substr($msg, 0, strpos($msg, ','));
                $vars = explode(',', $msg);
                Analog::log("Product variables: $msg");
                $choices = array_slice($vars, 1);
                $id_product_attribute = $this->getAttribute($product, $choices);
                Analog::log("Product combination: $id_product_attribute");
                $combo_groups = $this->getCombination($id_product_attribute);
                if (!empty($combo_groups) && is_array($combo_groups) && $combo_groups) {
                    foreach ($combo_groups as $k => $row) {
                        $combinations = $this->buildAttributes($id_product_attribute, $row);
                    }
                }
                $client->send(json_encode($combinations));
            }
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
    private function buildAttributes($id_product_attribute, $row)
    {
        $combinations = array();
        $combinations[$row['id_product_attribute']]['id_product'] = (int) $row['id_product'];
        $combinations[$row['id_product_attribute']]['name'] = $row['product_name'];
        $combinations[$row['id_product_attribute']]['attributes_values'][$row['id_attribute_group']] = $row['attribute_name'];
        $combinations[$row['id_product_attribute']]['attributes'][] = (int) $row['id_attribute'];
        $combinations[$row['id_product_attribute']]['base_price'] = (float) $row['base_price'];
        $combinations[$row['id_product_attribute']]['price'] = (float) $row['price'];

                // Call getPriceStatic in order to set $combination_specific_price
                if (!isset($combinationSet[(int) $row['id_product_attribute']])) {
                    $specific_price = $this->getSpecificPrice($id_product_attribute, $row['id_product']);
                    $combinationSet[(int) $row['id_product_attribute']] = true;
                    $combinations[$row['id_product_attribute']]['specific_price'] = $specific_price;
                }
        $combinations[$row['id_product_attribute']]['ecotax'] = (float) $row['ecotax'];
        $combinations[$row['id_product_attribute']]['weight'] = (float) $row['weight'];
        $combinations[$row['id_product_attribute']]['quantity'] = (int) $row['quantity'];
        $combinations[$row['id_product_attribute']]['reference'] = $row['reference'];
        $combinations[$row['id_product_attribute']]['unit_impact'] = $row['unit_price_impact'];
        $combinations[$row['id_product_attribute']]['minimal_quantity'] = $row['minimal_quantity'];
        $combinations[$row['id_product_attribute']]['available_date'] = '';
        if ($row['available_date'] != '0000-00-00') {
            $combinations[$row['id_product_attribute']]['available_date'] = $row['available_date'];
        }
        $combinations[$row['id_product_attribute']]['image'] = $this->getComboImage($id_product_attribute);
        $combinations[$row['id_product_attribute']]['final_price'] = $this->getFinalPrice($row, $specfic_price);

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

    /**
     * @param null|string $id_product_attribute
     */
    private function getCombination($id_product_attribute)
    {
        $sql = 'SELECT ag.id_attribute_group, ag.is_color_group, agl.name AS group_name, agl.public_name AS public_group_name,
                    a.id_attribute, al.name AS attribute_name, a.color AS attribute_color, pas.id_product_attribute,
                    IFNULL(stock.quantity, 0) as quantity, pas.price, pas.ecotax, pas.weight,
                    pas.default_on, pa.reference, pas.unit_price_impact,
                    pas.minimal_quantity, pas.available_date, ag.group_type
                FROM '._DB_PREFIX_.'product_attribute pa
                 INNER JOIN '._DB_PREFIX_.'product_attribute_shop pas
                    ON (pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = 1)
                 LEFT JOIN '._DB_PREFIX_.'stock_available stock
                        ON (stock.id_product = pa.id_product AND stock.id_product_attribute = IFNULL(pa.id_product_attribute, 0) AND stock.id_shop = 1  )
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

    private function getSpecificPrice($id_product_attribute, $id_product)
    {
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

    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        if ($conn instanceof resourceId) {
        Analog::log('Connection '.$conn->resourceId.' has disconnected');
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        Analog::log('Error: '.$e->getMessage().'');
        $conn->close();
    }
}
