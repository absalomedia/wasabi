<?php

namespace ABM\Wasabi;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Analog\Analog;

class Cart implements MessageComponentInterface
{
    protected $clients;

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
        $log_file = 'ws-prod.log';
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
                $cart = substr($msg, 0, strpos($msg, ','));
                $cust = substr($msg, strpos($msg, ',') + 1);

                Analog::log("Cart & customer variables: $msg");

                $otherCarts = $this->processFindCarts($cart, $cust);
            }
        }

            // Basic test - fire the correct combination back via Websocket
            $client->send(json_encode($otherCarts));
    }

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
