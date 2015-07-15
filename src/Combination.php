<?php
namespace WS\Wasabi;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Analog\Analog;

if(@include "config/settings.inc.php") 
  $triplepath = "./";
else if (@include "../config/settings.inc.php")
  $triplepath = "../";
else if (@include "../../config/settings.inc.php")
  $triplepath = "../../";
else
  die( "<p><b>Unable to find Prestashop config file. Please rectify.\n");


class Combination implements MessageComponentInterface {

    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        // Now connect to PS DB using Simplon on Composer
        $this->dbConn = new \Simplon\Mysql\Mysql(
    '127.0.0.1',
    _DB_USER_,
    _DB_PASSWD_,
    _DB_NAME_
        );
        $sqlManager = new \Simplon\Mysql\Manager\SqlManager($this->dbConn);
        $log_file = 'ws-comb.log';
        Analog::handler (\Analog\Handler\File::init ($log_file));
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        Analog::log ("New connection: $conn->resourceId");
    }

    public function onMessage(ConnectionInterface $from, $msg) {
            
        foreach ($this->clients as $client) {

            if ($from == $client) {

            $product = substr($msg, 0, strpos($msg, ','));
            $vars = explode(',', $msg);

            Analog::log ("Product variables: $msg");

            $choices = array_slice($vars, 1);

           // echo '<pre>',print_r($choices,1),'</pre>';


            // Find combination
            $sql = 'SELECT pac.id_product_attribute from '._DB_PREFIX_.'product_attribute_combination as pac
                LEFT JOIN  '._DB_PREFIX_.'product_attribute as ppa on ppa.id_product_attribute = pac.id_product_attribute
                LEFT JOIN `'._DB_PREFIX_.'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
                WHERE ppa.id_product = '.$product;
             foreach ($choices as $value) {
            $sql .= ' AND pac.id_product_attribute IN
                    ( SELECT pac.id_product_attribute from '._DB_PREFIX_.'product_attribute_combination as pac
                    WHERE pac.id_attribute = '.(int)$value.')  ';
                    }
            $sql .= ' GROUP BY pac.id_product_attribute';

            // echo $sql."\n";

            $id_product_attribute = $this->dbConn->fetchColumn($sql);
        
            // $client->send($id_product_attribute);
            Analog::log ("Product combination: $id_product_attribute");

            $sql = 'SELECT ag.`id_attribute_group`, ag.`is_color_group`, agl.`name` AS group_name, agl.`public_name` AS public_group_name,
                    a.`id_attribute`, al.`name` AS attribute_name, a.`color` AS attribute_color, product_attribute_shop.`id_product_attribute`,
                    IFNULL(stock.quantity, 0) as quantity, product_attribute_shop.`price`, product_attribute_shop.`ecotax`, product_attribute_shop.`weight`,
                    product_attribute_shop.`default_on`, pa.`reference`, product_attribute_shop.`unit_price_impact`,
                    product_attribute_shop.`minimal_quantity`, product_attribute_shop.`available_date`, ag.`group_type`
                FROM `'._DB_PREFIX_.'product_attribute` pa
                 INNER JOIN '._DB_PREFIX_.'product_attribute_shop product_attribute_shop
                    ON (product_attribute_shop.id_product_attribute = pa.id_product_attribute AND product_attribute_shop.id_shop = 1)
                 LEFT JOIN '._DB_PREFIX_.'stock_available stock
                        ON (stock.id_product = pa.id_product AND stock.id_product_attribute = IFNULL(`pa`.id_product_attribute, 0) AND stock.id_shop = 1  )
                LEFT JOIN `'._DB_PREFIX_.'product_attribute_combination` pac ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
                LEFT JOIN `'._DB_PREFIX_.'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
                LEFT JOIN `'._DB_PREFIX_.'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
                LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute`)
                LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group`)
                 INNER JOIN '._DB_PREFIX_.'attribute_shop attribute_shop
                    ON (attribute_shop.id_attribute = a.id_attribute AND attribute_shop.id_shop = 1)
                WHERE product_attribute_shop.`id_product_attribute`= '.(int)$id_product_attribute.'
                GROUP BY id_attribute_group, id_product_attribute
                ORDER BY ag.`position` ASC, a.`position` ASC, agl.`name` ASC';


            $combo_groups = $this->dbConn->fetchRowMany($sql);


            if (is_array($combo_groups) && $combo_groups)
            {
            $combination_prices_set = array();
            foreach ($combo_groups as $k => $row)
            {

                $combinations[$row['id_product_attribute']]['attributes_values'][$row['id_attribute_group']] = $row['attribute_name'];
                $combinations[$row['id_product_attribute']]['attributes'][] = (int)$row['id_attribute'];
                $combinations[$row['id_product_attribute']]['price'] = (float)$row['price'];

                // Call getPriceStatic in order to set $combination_specific_price
                if (!isset($combination_prices_set[(int)$row['id_product_attribute']]))
                {

                    $sql = 'SELECT COUNT(*) FROM '._DB_PREFIX_.'specific_price WHERE id_product_attribute = '.(int)$id_product_attribute;
                    $spec = $this->dbConn->fetchColumn($sql);

                    if ($spec > 0) {

                        $now = date('Y-m-d H:i:s');
                        $query = 'SELECT * FROM `'._DB_PREFIX_.'specific_price`
                            WHERE `id_product_attribute` IN (0, '.(int)$id_product_attribute.')
                            AND (
                                   (`from` = \'0000-00-00 00:00:00\' OR \''.$now.'\' >= `from`)
                            AND
                                     (`to` = \'0000-00-00 00:00:00\' OR \''.$now.'\' <= `to`)
                            ) ';

                         $query .= ' ORDER BY `id_product_attribute` DESC, `from_quantity` DESC, `id_specific_price_rule` ASC';
            
                        $result = $this->dbConn->fetchRow($query);

                      $specific_price['price'] = $result['price'];
                      $specific_price['id_product_attribute'] = $result['id_product_attribute'];
                      $specific_price['reduction_percent'] = (int) 100 * $result['reduction'];
                      $specific_price['reduction_price'] = 0;
                      $specific_price['reduction_type'] = $result['reduction_type'];
                    } else { 
                      $specific_price['price'] = 0;
                      $specific_price['id_product_attribute'] = 0;
                      $specific_price['reduction_percent'] = 0;
                      $specific_price['reduction_price'] = 0;
                      $specific_price['reduction_type'] = '';
                    }
                   $combination_prices_set[(int)$row['id_product_attribute']] = true;
                   $combinations[$row['id_product_attribute']]['specific_price'] = $specific_price;
                }

                $combinations[$row['id_product_attribute']]['ecotax'] = (float)$row['ecotax'];
                $combinations[$row['id_product_attribute']]['weight'] = (float)$row['weight'];
                $combinations[$row['id_product_attribute']]['quantity'] = (int)$row['quantity'];
                $combinations[$row['id_product_attribute']]['reference'] = $row['reference'];
                $combinations[$row['id_product_attribute']]['unit_impact'] = $row['unit_price_impact'];
                $combinations[$row['id_product_attribute']]['minimal_quantity'] = $row['minimal_quantity'];
                if ($row['available_date'] != '0000-00-00')
                    $combinations[$row['id_product_attribute']]['available_date'] = $row['available_date'];
                else
                    $combinations[$row['id_product_attribute']]['available_date'] = '';


                $sql = 'SELECT pai.`id_image`as image
                        FROM `'._DB_PREFIX_.'product_attribute_image` pai
                        LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (il.`id_image` = pai.`id_image`)
                        LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_image` = pai.`id_image`)
                        WHERE pai.`id_product_attribute` = '.(int)$id_product_attribute. ' ORDER by i.`position`';
                 $image = $this->dbConn->fetchColumn($sql);
                 if ($image != false) {
                    $combinations[$row['id_product_attribute']]['image'] = (int)$image;
                 } else {
                    $combinations[$row['id_product_attribute']]['image'] = -1;
                 }

            }   

        }

            // Analog::log ("Combination: ".json_encode($combinations)." \n");

            // Basic test - fire the correct combination back via Websocket
            $client->send(json_encode($combinations));

            // The sender is not the receiver, send to each client connected
            //   $client->send($id_product_attribute);
            }
        }

    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        Analog::log ("Connection ".$conn->resourceId." has disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        Analog::log ("Error: ".$e->getMessage()."");
        $conn->close();
    }
}