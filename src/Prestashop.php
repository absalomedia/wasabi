<?php

namespace ABM\Wasabi;

use Analog\Analog;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

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
        if (resourceId instanceof $conn) {
            Analog::log("New connection: $conn->resourceId");
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $result = null;
        foreach ($this->clients as $client) {
            if ($from == $client) {
                $type = strtolower(substr($msg, 0, strpos($msg, '|')));
                $data = substr($msg, strpos($msg, '|') + 1);
                switch ($type) {
                    case 'cart': $result = Cart::getCartData($data);
                                    break;
                    case 'prod': $result = Product::getProductData($data);
                                    break;
                    case 'comb': $result = Combination::getCombinationData($data);
                                    break;
                    default:        break;
                }
                if (!empty($result)) {
                    $client->send(json_encode($result));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        if (resourceId instanceof $conn) {
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
