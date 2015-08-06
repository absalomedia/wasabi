<?php
namespace WS\Wasabi;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Analog\Analog;

$filename = $root.'/config/settings.inc.php';

if (file_exists($filename)) {
    include $filename;
} else {
    trigger_error("Unable to find Prestashop config file. Please rectify", E_USER_ERROR);
}


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
        $sqlManager = new \Simplon\Mysql\Manager\SqlManager($this->dbConn);
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

            $product = substr($msg, 0, strpos($msg, ','));
            $vars = explode(',', $msg);

            Analog::log ("Product variables: $msg");

            $choices = array_slice($vars, 1);

            }   

        }

            // Analog::log ("Combination: ".json_encode($combinations)." \n");

            // Basic test - fire the correct combination back via Websocket
            $client->send();

            // The sender is not the receiver, send to each client connected
            //   $client->send($id_product_attribute);
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