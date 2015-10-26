<?php

namespace AB\Wasabi;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Analog\Analog;

class Product implements MessageComponentInterface {

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
        $log_file = 'ws-prod.log';
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

            $category = (int) substr($msg, 0, strpos($msg, ','));
            if ($category != 0) {
                $products = $this->getProducts($category);
            } else {
                $product = substr($msg, strpos($msg, ",") + 1);
                $products = $this->getProducts($product);
            }           
            Analog::log ("Product variables: $msg");
            }   

        }

            // Basic test - fire the correct product back via Websocket
            $client->send(json_encode($products));

    }

    private function getProducts($category)
    {
            $product_ids = $this->getProductIDs($category);
            $products = $this->getProduct($product_ids);

            return $products;
    }


    private function getProductIDs($category)
    {
        $sql = 'SELECT DISTINCT p.id_product
                from '._DB_PREFIX_.'product as p
                LEFT JOIN '._DB_PREFIX_.'image AS i ON i.id_product = p.id_product 
                LEFT JOIN '._DB_PREFIX_.'product_lang as pl ON pl.id_product = p.id_product
                WHERE p.active = 1
                AND p.id_category_default = '.(int)$category.'
                GROUP BY p.id_product';
        $pcats = $this->dbConn->fetchRowMany($sql);
        $ids = '';
        foreach ($pcats as $row) {
            $ids .= $row['id_product'].',';
        }
        $ids = rtrim($ids, ",");

        return $ids;
    }

    private function getProduct($ids)
    {
        $sql = 'SELECT p.id_product, p.id_supplier, p.ean13, p.upc, p.price, p.wholesale_price, p.on_sale, p.quantity, p.id_category_default, p.show_price, p.available_for_order, p.minimal_quantity, p.customizable,
                    p.out_of_stock, pl.link_rewrite, pl.name, i.id_image, il.legend,
                    cl.name AS category_default,  cl.id_category AS cat_id,
                    ps.price AS orderprice
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
    
        $result = $this->dbConn->fetchRowMany($sql);

        return $result;
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
