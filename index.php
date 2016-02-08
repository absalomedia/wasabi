<?php

require 'vendor/autoload.php';

// Your shell script
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use ABM\Wasabi\Prestashop;

    $wasabi = new WsServer(new Prestashop());
    $wasabi->disableVersion(0); // old, bad, protocol version
    $wasabi->setEncodingChecks(false);

    // Make sure you're running this as root
    $server = IoServer::factory(new HttpServer($wasabi), 8787, '0.0.0.0');
    $server->run();
